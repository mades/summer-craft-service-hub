<?php

namespace SummerCraft\Service\Logger;

use Exception;
use RuntimeException;
use SummerCraft\Service\Modifier\StringModifier;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;

/**
 * Writes log messages to STDOUT/STDERR instead of per-tag/per-level files —
 * for a 12-factor/containerized deployment where a log aggregator collects
 * whatever the process writes to its standard streams, instead of files that
 * would need a mounted volume and app-level rotation (see FileLogger).
 *
 * Uses php://stdout and php://stderr rather than the STDOUT/STDERR constants:
 * those constants are only predefined under the CLI (and CGI -f) SAPI, not
 * under php-fpm/mod_php — referencing them directly would fatal under a web
 * SAPI. The php:// stream wrappers work under every SAPI.
 */
class StdOutLogger implements TaggedLogger, SharedComponent
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

    /**
     * Ordered from most to least severe (except the leading 'none' sentinel,
     * used when no level was given at all — see log()).
     * @var string[]
     */
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

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    public function __construct(
        protected LoggerConfig $config,
    ) {
        $this->enableLevels = [];
        foreach ($this->levels as $levelValue) {
            $this->enableLevels[] = $levelValue;
            if ($levelValue === $this->config->threshold) {
                break;
            }
        }

        $this->stdout = fopen('php://stdout', 'ab');
        $this->stderr = fopen('php://stderr', 'ab');
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

        $this->writeToStream($level, $this->formatMessage($level, $tag, $message, $context));
    }

    private function isAtOrAboveThreshold(string $level, string $threshold): bool
    {
        $levelIndex = array_search($level, $this->levels, true);
        $thresholdIndex = array_search($threshold, $this->levels, true);
        if ($levelIndex === false || $thresholdIndex === false) {
            return false;
        }
        // lower index = more severe (see $levels ordering above)
        return $levelIndex <= $thresholdIndex;
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

    /**
     * FileLogger encodes the tag into the file name and drops it from the
     * message body — there's no file name here, so 'default'/custom-template
     * formats prepend it to the message instead, to avoid silently losing it.
     */
    private function formatMessage(string $level, string $tag, string $message, array $context): string
    {
        $date = date($this->config->dateFormat);

        if ($this->config->format === 'json') {
            return json_encode(
                ['level' => $level, 'date' => $date, 'tag' => $tag, 'message' => $message, 'context' => $context],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $contextString = !empty($context) ? print_r($context, true) : '';

        if ($this->config->format === 'default') {
            $body = $tag !== '' ? "[$tag] $message" : $message;
            if ($contextString !== '') {
                $body .= "\n\n" . $contextString;
            }
            return "[$level | $date] $body";
        }

        // custom template: {level} {date} {tag} {message} {context} placeholders
        return str_replace(
            ['{level}', '{date}', '{tag}', '{message}', '{context}'],
            [$level, $date, $tag, $message, $contextString],
            $this->config->format
        );
    }

    private function writeToStream(string $level, string $formattedMessage): void
    {
        $stream = $this->stdout;
        if ($this->config->stdoutSplitStreams && $this->isAtOrAboveThreshold($level, $this->config->stdoutSplitThreshold)) {
            $stream = $this->stderr;
        }

        fwrite($stream, $formattedMessage . "\n");
    }
}
