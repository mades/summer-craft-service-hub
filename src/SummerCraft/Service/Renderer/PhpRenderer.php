<?php

namespace SummerCraft\Service\Renderer;

use RuntimeException;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Modifier\StringModifier;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;
use SummerCraft\Core\Http\ResponseAccumulator;
use Throwable;

/**
 * Request-scoped rather than Shared on purpose: $initLevel/$inProgressRequestId/
 * $cachedData are per-request state. A fresh instance per request means
 * $initLevel is always a correct snapshot for *this* request instead of one taken
 * once when a Shared singleton was constructed (which would drift after any prior
 * request left its own ob_start() buffer open).
 *
 * This does not, by itself, make rendering safe under cooperative multitasking
 * (ReactPHP/Fibers/coroutines) — ob_start()/ob_get_contents()/ob_end_clean() operate
 * on one global, process-wide buffer stack; PHP has no per-fiber buffering. As long
 * as a view never yields control (no async I/O) between this class's ob_start() and
 * its matching ob_end_*(), the pair is atomic from the event loop's perspective and
 * stays safe. If a view ever performs async work mid-render, two concurrently
 * in-flight renders could resume out of LIFO order and corrupt each other's output —
 * fixing that would mean not using ob_* at all (templates returning strings, like
 * Twig/Blade compile to, instead of echo-ing into a shared buffer), which is a
 * larger rendering-mechanism change, not a lifecycle tweak.
 */
class PhpRenderer implements Renderer, RequestScopeComponent
{
    protected int $initLevel;

    protected ?string $inProgressRequestId = null;

    protected array $cachedData = [];

    public function __construct(
        private RendererConfig $config,
        private Logger $logger,
    ) {
        $this->initLevel = ob_get_level();
    }

    public function getInitLevel(): int
    {
        return $this->initLevel;
    }

    /**
     * {@inheritDoc}
     */
    public function render(RequestScope $requestScope, string $viewFile, array $data = [], bool $return = false): string
    {
        if ($this->inProgressRequestId !== null) {
            $concurrencyResolverCounter = 0;
            while ($this->inProgressRequestId !== $requestScope->getIdentity()->getId()) {
                $this->logger->warning("Concurrency detected in PhpRenderer. resolving...", ['tag' => 'PHP_RENDERER']);
                usleep(30000);
                $concurrencyResolverCounter++;
                if ($concurrencyResolverCounter === 100) {
                    throw new RuntimeException("Can not resolve concurrency in PhpRenderer");
                }
            }
        }
        if ($this->inProgressRequestId === null) {
            $this->inProgressRequestId = $requestScope->getIdentity()->getId();
        }

        $_view_file = $viewFile;

        if (strpos($_view_file, $this->config->templateNameAppend) === false) {
            $_view_file .= $this->config->templateNameAppend;
        }

        if (!file_exists($_view_file)) {
            ExceptionProcessor::defaultProcessException(
                new \RuntimeException('Template [' . $_view_file . '] not found to process'),
                $requestScope
            );
        }

        if (!empty($data)) {
            $this->cachedData = array_merge($this->cachedData, $data);
        }

        /**
         * View Data
         */
        extract($this->cachedData, EXTR_OVERWRITE);
        $scope = $requestScope;
        $renderer = $this;

        ob_start();

        try {
            include $_view_file;
        } catch (Throwable $throwable) {
            // Without this, a template that throws leaves its ob_start() buffer open
            // forever. Harmless under classic per-request PHP (the whole process, and
            // so the whole output-buffer stack, gets torn down anyway) — but if
            // PhpRenderer were ever made a Shared singleton instead of RequestScope
            // (see class docblock for why it deliberately isn't), the same instance
            // would keep running for many requests under a worker model (RoadRunner):
            // $this->initLevel would then permanently disagree with the real
            // ob_get_level(), and every later request's nested-vs-top-level detection
            // below would silently misbehave for the rest of that worker's life. Same
            // reasoning for inProgressRequestId — left set, it would make the next
            // request in this worker spin in the concurrency-wait loop above until it
            // times out.
            $isOutermostCall = ob_get_level() <= $this->initLevel + 1;
            @ob_end_clean();
            if ($isOutermostCall) {
                $this->inProgressRequestId = null;
                $this->cachedData = [];
            }
            throw $throwable;
        }

        /*
         * Flush the buffer... or buff the flusher?
         *
         * In order to permit views to be nested within
         * other views, we need to flush the content back out whenever
         * we are beyond the first level of output buffering so that
         * it can be seen and included properly by the first included
         * template and any subsequent ones. Oy!
         *
         * ReactPHP/Fiber concurrency caveat: see the class docblock.
         */
        if (ob_get_level() > $this->initLevel + 1) {
            ob_end_flush();
        } else {
            $content = ob_get_contents();
            @ob_end_clean();

            $this->inProgressRequestId = null;
            $this->cachedData = [];

            if ($return === true) {
                return $content;
            } else {
                $requestScope->get(ResponseAccumulator::class)->append($content);
            }
        }
        return '';
    }

    /**
     * Html Encode (HtmlSpecialChars)
     */
    public function textForHtml(string $string): string
    {
        return StringModifier::textForHtml($string);
    }
}
