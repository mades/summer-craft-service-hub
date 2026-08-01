<?php

namespace SummerCraft\Service\Logger;

use Exception;
use RuntimeException;
use SummerCraft\Service\Modifier\StringModifier;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;

class FileLogger implements TaggedLogger, SharedComponent
{
    private const LEVEL_NONE = 'none';
    private const LEVEL_EMERGENCY = 'emergency';
    private const LEVEL_ALERT = 'alert';
    private const LEVEL_CRITICAL = 'critical';
    private const LEVEL_ERROR = 'error';
    private const LEVEL_WARNING = 'warning';
    private const LEVEL_NOTICE = 'notice';
    private const LEVEL_INFO = 'info';
    private const LEVEL_DEBUG = 'debug';

    protected array $levels = [
        self::LEVEL_NONE,
        self::LEVEL_EMERGENCY,
        self::LEVEL_ALERT,
        self::LEVEL_CRITICAL,
        self::LEVEL_ERROR,
        self::LEVEL_WARNING,
        self::LEVEL_NOTICE,
        self::LEVEL_INFO,
        self::LEVEL_DEBUG,
    ];

    /**
     * @var string[]
     */
    protected array $enableLevels;

    /**
     * @var array
     */
    private array $holdedMessages = [];


    public function __construct(
        protected LoggerConfig $config,
    ) {
        $this->enableLevels = [];
        foreach($this->levels as $levelValue) {
            $this->enableLevels[] = $levelValue;
            if ($levelValue === $this->config->threshold) {
                break;
            }
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->internalLog($level, $context[LoggerContext::TAG] ?? '', $message, $context);
    }

    public function taggedEmergency(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('emergency', $tag, $message, $context);
    }

    public function taggedAlert(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('alert', $tag, $message, $context);
    }

    public function taggedCritical(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('critical', $tag, $message, $context);
    }

    public function taggedError(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('error', $tag, $message, $context);
    }

    public function taggedWarning(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('warning', $tag, $message, $context);
    }

    public function taggedNotice(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('notice', $tag, $message, $context);
    }

    public function taggedInfo(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('info', $tag, $message, $context);
    }

    public function taggedDebug(string $tag, string $message, array $context = []): void
    {
        $this->internalLog('debug', $tag, $message, $context);
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('emergency', '', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('alert', '', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('critical', '', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('error', '', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('warning', '', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('notice', '', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('info', '', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->internalLog('debug', '', $message, $context);
    }

    private function internalLog(string $level, string $tag, string $message, array $context): void
    {
        if ($context[LoggerContext::TAG] ?? null) {
            $tag = $context[LoggerContext::TAG];
        }
        $filteredTag = StringModifier::filterChars($tag, StringModifier::FILTER_BASIC_EN);
        if ($tag !== $filteredTag) {
            throw new RuntimeException("Invalid tag to logging [$tag]");
        }
        if ($context[LoggerContext::WITH_TRACE] ?? null) {
            $this->processTraceInLogContext($context);
        }
        if ($context[LoggerContext::EXCEPTION] ?? null) {
            $this->processExceptionsInLogContext($context);
        }

        if ($level === '') {
            $level = self::LEVEL_NONE;
        }

        if (!in_array($level, $this->enableLevels, true)) {
            return;
        }

        $file = ($this->config->datePrepend ? date($this->config->datePrepend). '_' : '')
            . str_replace(['{#tag}', '{#level}'], [$tag,     $level], $this->config->byFile) . '.log';

        $fullMessage = $this->contextToString($message, $context);

        if ($this->config->holdToViewMessages) {
            $this->holdedMessages[] = "$file | $level | $fullMessage";
        }

        $this->writeToFile($this->config->directory . $file, $level, $fullMessage);
    }

    /**
     * @return string[]
     */
    public function getHeldMessages(): array
    {
        return $this->holdedMessages;
    }

    private function processTraceInLogContext(array &$context): void
    {
        $withTrace = $context[LoggerContext::WITH_TRACE] ?? null;
        if ($withTrace === null) {
            return;
        }
        $e = new Exception;
        $traceData = [];
        $traceArray = explode("\n",$e->getTraceAsString());
        foreach($traceArray as $key => $traceArrayValue) {
            $traceData['#'.$key] = $traceArrayValue;
        }
        $context[LoggerContext::WITH_TRACE] = $traceData;
    }

    private function processExceptionsInLogContext(array &$context): void
    {
        $contextException = $context[LoggerContext::EXCEPTION] ?? null;
        if ($contextException === null) {
            return;
        }
        $exceptions = [$contextException];
        $exceptions = array_merge($exceptions, ExceptionProcessor::extractExceptions($contextException));
        unset($context[LoggerContext::EXCEPTION]);
        $context['exceptions'] = [];
        foreach($exceptions as $exception) {
            $context['exceptions'][] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
    }

    private function contextToString(string $message, array $context): string
    {
        $result = $message;

        if (!empty($context)) {
            $result .= "\n\n" . print_r($context, true);
        }

        return $result;
    }

    private function writeToFile(string $file, string $level, string $message): void
    {
        $isNewFile = false;
        if (!file_exists($file)) {
            $isNewFile = true;
        }

        $fp = fopen($file, 'ab');
        flock($fp, LOCK_EX);
        $timeString = '[' . $level . ' | ' . date($this->config->dateFormat) . ']';
        fwrite($fp, $timeString .' '. $message ."\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($isNewFile) {
            chmod($file, $this->config->filePermission);
        }
    }
}
