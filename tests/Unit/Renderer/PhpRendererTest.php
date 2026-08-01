<?php

namespace SummerCraft\Service\Tests\Unit\Renderer;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Service\Logger\Logger;
use SummerCraft\Service\Renderer\PhpRenderer;
use SummerCraft\Service\Renderer\RendererConfig;
use SummerCraft\Service\Tests\Fixture\NullLogger;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Http\ResponseAccumulator;

/**
 * render() with $return=false used to call
 * $controller->response->append() — $controller was never defined anywhere in the
 * method, relying on an undocumented convention that view $data always carries a
 * 'controller' key. The only real caller, one layer up, always passes
 * $return=true, so this path was never actually exercised.
 */
class PhpRendererTest extends TestCase
{
    private string $viewFile;

    protected function setUp(): void
    {
        $this->viewFile = tempnam(sys_get_temp_dir(), 'view') . '.tpl.php';
        file_put_contents($this->viewFile, '<?php echo "hello view"; ?>');
    }

    protected function tearDown(): void
    {
        @unlink($this->viewFile);
    }

    private function scope(): RequestScope
    {
        $config = new Config();
        $config->services[Logger::class] = ComponentConfig::forClass(NullLogger::class);
        return new RequestScope(new ComponentHolder($config));
    }

    private function renderer(): PhpRenderer
    {
        return new PhpRenderer(new RendererConfig(), new NullLogger());
    }

    public function testRenderWithoutReturnAppendsToResponseAccumulator(): void
    {
        $scope = $this->scope();

        $result = $this->renderer()->render($scope, $this->viewFile, [], false);

        self::assertSame('', $result);
        self::assertSame('hello view', $scope->get(ResponseAccumulator::class)->content());
    }

    public function testRenderWithReturnTrueReturnsContentDirectlyWithoutTouchingAccumulator(): void
    {
        $scope = $this->scope();

        $result = $this->renderer()->render($scope, $this->viewFile, [], true);

        self::assertSame('hello view', $result);
        self::assertSame('', $scope->get(ResponseAccumulator::class)->content());
    }

    public function testRenderPassesDataToView(): void
    {
        file_put_contents($this->viewFile, '<?php echo "hello, {$name}!"; ?>');
        $scope = $this->scope();

        $result = $this->renderer()->render($scope, $this->viewFile, ['name' => 'world'], true);

        self::assertSame('hello, world!', $result);
    }

    /**
     * Regression tests for the ob-buffer leak: a view
     * that throws used to leave its ob_start() buffer open forever. PhpRenderer used to
     * be a Shared singleton, so under a worker model (RoadRunner) the same instance kept
     * handling requests after a crash — a leaked buffer level and a leaked
     * inProgressRequestId lock would corrupt every later request in that worker. Now
     * RequestScopeComponent (see below), so a fresh instance is normal per request — but
     * the exception-safety fix is kept and tested independently of that, since an
     * instance could still be reused explicitly (or from a nested render() call).
     */
    public function testExceptionInViewPropagatesAndDoesNotLeakOutputBuffer(): void
    {
        $throwingView = tempnam(sys_get_temp_dir(), 'view') . '.tpl.php';
        file_put_contents($throwingView, '<?php throw new \RuntimeException("boom");');

        $levelBefore = ob_get_level();
        try {
            $this->renderer()->render($this->scope(), $throwingView, [], true);
            self::fail('expected exception was not thrown');
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        } finally {
            @unlink($throwingView);
        }

        self::assertSame($levelBefore, ob_get_level());
    }

    public function testExceptionInViewReleasesLockForNextRequestOnSameRendererInstance(): void
    {
        $renderer = $this->renderer();
        $throwingView = tempnam(sys_get_temp_dir(), 'view') . '.tpl.php';
        file_put_contents($throwingView, '<?php throw new \RuntimeException("boom");');

        try {
            $renderer->render($this->scope(), $throwingView, [], true);
            self::fail('expected exception was not thrown');
        } catch (RuntimeException $exception) {
            // expected — the crash itself is not what's under test here
        } finally {
            @unlink($throwingView);
        }

        // a different request hitting the same (worker-reused) renderer instance
        // right after the crash must not be stuck waiting on a lock nobody released
        $start = microtime(true);
        $result = $renderer->render($this->scope(), $this->viewFile, [], true);
        $elapsed = microtime(true) - $start;

        self::assertSame('hello view', $result);
        self::assertLessThan(1.0, $elapsed);
    }

    /**
     * PhpRenderer is RequestScopeComponent (not Shared) specifically so that each
     * request gets a fresh $initLevel/$inProgressRequestId/$cachedData instead of a
     * singleton's stale, worker-lifetime snapshot — confirm the DI container actually
     * hands out a new instance per request scope, and the same one within one scope.
     */
    public function testDifferentRequestScopesGetDifferentRendererInstances(): void
    {
        $scopeA = $this->scope();
        $scopeB = $this->scope();

        $rendererA1 = $scopeA->get(PhpRenderer::class);
        $rendererA2 = $scopeA->get(PhpRenderer::class);
        $rendererB = $scopeB->get(PhpRenderer::class);

        self::assertSame($rendererA1, $rendererA2);
        self::assertNotSame($rendererA1, $rendererB);
    }
}
