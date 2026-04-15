<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Http\Kernel;
use App\Maintenance\MaintenanceMode;
use Myxa\Application;
use Myxa\Http\ExceptionHandlerInterface;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;
use Throwable;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    private string $errorLogPath;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorLogPath = sys_get_temp_dir() . '/myxa-kernel-log-' . uniqid('', true) . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->errorLogPath);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);

        if (is_file($this->errorLogPath)) {
            unlink($this->errorLogPath);
        }

        parent::tearDown();
    }

    public function testKernelNormalizesStringResponsesToHtmlForBrowserRequests(): void
    {
        $kernel = $this->makeKernelWithRoute(static fn (): string => '<main>hello</main>');

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('<main>hello</main>', $response->content());
    }

    public function testKernelNormalizesStringResponsesToJsonWhenRequestExpectsJson(): void
    {
        $kernel = $this->makeKernelWithRoute(static fn (): string => 'pong');

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('"pong"', $response->content());
    }

    public function testKernelNormalizesNullResponsesToNoContent(): void
    {
        $kernel = $this->makeKernelWithRoute(static fn (): null => null);

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]));

        self::assertSame(204, $response->statusCode());
        self::assertSame('', $response->content());
        self::assertNull($response->header('Content-Type'));
    }

    public function testKernelNormalizesScalarResponsesToPlainText(): void
    {
        $kernel = $this->makeKernelWithRoute(static fn (): int => 123);

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('123', $response->content());
    }

    public function testKernelRendersReportFailureInsteadOfOriginalException(): void
    {
        $handler = new class implements ExceptionHandlerInterface {
            public function report(Throwable $exception): void
            {
                throw new \RuntimeException('logger write failed');
            }

            public function render(Throwable $exception, Request $request): Response
            {
                return (new Response())->text(
                    $exception->getMessage(),
                    $exception instanceof \RuntimeException ? 500 : 400,
                );
            }
        };

        $kernel = $this->makeKernelWithRoute(
            static fn (): never => throw new \InvalidArgumentException('original failure'),
            $handler,
        );

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]));

        self::assertSame(500, $response->statusCode());
        self::assertSame('logger write failed', $response->content());
    }

    public function testKernelFallsBackToEmergencyJsonResponseWhenRenderFails(): void
    {
        $handler = new class implements ExceptionHandlerInterface {
            public function report(Throwable $exception): void
            {
            }

            public function render(Throwable $exception, Request $request): Response
            {
                throw new \RuntimeException('render failed');
            }
        };

        $kernel = $this->makeKernelWithRoute(
            static fn (): never => throw new \RuntimeException('boom'),
            $handler,
        );

        $response = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(500, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame(
            '{"error":{"type":"server_error","message":"Server Error","status":500}}',
            $response->content(),
        );
    }

    public function testKernelFallsBackToEmergencyTextResponseWhenHandlerCannotBeResolved(): void
    {
        $app = new Application();
        new Router($app);

        $app->make(Router::class)->get(
            '/test',
            static fn (): never => throw new \RuntimeException('boom'),
        );

        $response = (new Kernel($app))->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]));

        self::assertSame(500, $response->statusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('Server Error', $response->content());
    }

    public function testKernelShortCircuitsBrowserRequestsDuringMaintenanceMode(): void
    {
        $maintenance = new MaintenanceMode(base_path());
        $maintenance->enable('phpunit');

        try {
            $kernel = $this->makeKernelWithRoute(static fn (): string => 'reachable');

            $response = $kernel->handle(new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/test',
            ]));

            self::assertSame(503, $response->statusCode());
            self::assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
            self::assertStringContainsString('temporarily offline for maintenance', $response->content());
        } finally {
            $maintenance->disable();
        }
    }

    public function testKernelShortCircuitsApiRequestsDuringMaintenanceMode(): void
    {
        $maintenance = new MaintenanceMode(base_path());
        $maintenance->enable('phpunit');

        try {
            $kernel = $this->makeKernelWithRoute(static fn (): array => ['ok' => true]);

            $response = $kernel->handle(new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/test',
                'HTTP_ACCEPT' => 'application/json',
            ]));

            self::assertSame(503, $response->statusCode());
            self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
            self::assertSame(
                '{"error":{"type":"maintenance_mode","message":"Service Unavailable","status":503}}',
                $response->content(),
            );
        } finally {
            $maintenance->disable();
        }
    }

    private function makeKernelWithRoute(callable $handler, ?ExceptionHandlerInterface $exceptionHandler = null): Kernel
    {
        $app = new Application();
        $router = new Router($app);

        $router->get('/test', $handler);

        if ($exceptionHandler !== null) {
            $app->instance(ExceptionHandlerInterface::class, $exceptionHandler);
        }

        return new Kernel($app);
    }
}
