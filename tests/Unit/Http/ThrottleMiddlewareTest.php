<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Config\ConfigRepository;
use App\Http\Middleware\ThrottleMiddleware;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\RateLimit\Exceptions\TooManyRequestsException;
use Myxa\RateLimit\FileRateLimiterStore;
use Myxa\RateLimit\RateLimiter;
use Myxa\Routing\RouteDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ThrottleMiddleware::class)]
final class ThrottleMiddlewareTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-throttle-middleware-' . uniqid('', true);
        mkdir($this->rootPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testUsingPresetAppliesHeadersToSuccessfulResponses(): void
    {
        $config = new ConfigRepository([
            'rate_limit' => [
                'presets' => [
                    'api' => [
                        'max_attempts' => 2,
                        'decay_seconds' => 60,
                        'prefix' => 'api',
                    ],
                ],
            ],
        ]);
        $limiter = new RateLimiter(new FileRateLimiterStore($this->rootPath . '/rate-limit'));
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/reports',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $route = new RouteDefinition(['GET'], '/reports', static fn (): Response => new Response());
        $middleware = ThrottleMiddleware::using('api');

        $response = $middleware(
            $request,
            static fn (): Response => (new Response())->json(['ok' => true]),
            $route,
            $limiter,
            $config,
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('2', $response->header('X-RateLimit-Limit'));
        self::assertSame('1', $response->header('X-RateLimit-Remaining'));
    }

    public function testUsingPresetThrowsWhenLimitIsExceeded(): void
    {
        $config = new ConfigRepository([
            'rate_limit' => [
                'presets' => [
                    'login' => [
                        'max_attempts' => 1,
                        'decay_seconds' => 60,
                        'prefix' => 'login',
                    ],
                ],
            ],
        ]);
        $limiter = new RateLimiter(new FileRateLimiterStore($this->rootPath . '/rate-limit'));
        $request = new Request(server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/login',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $route = new RouteDefinition(['POST'], '/login', static fn (): Response => new Response());
        $middleware = ThrottleMiddleware::using('login');

        $middleware(
            $request,
            static fn (): Response => (new Response())->text('ok'),
            $route,
            $limiter,
            $config,
        );

        $this->expectException(TooManyRequestsException::class);

        $middleware(
            $request,
            static fn (): Response => (new Response())->text('ok'),
            $route,
            $limiter,
            $config,
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
