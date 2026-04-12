<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Config\ConfigRepository;
use App\Http\ExceptionHandler;
use Myxa\Auth\Exceptions\AuthenticationException;
use Myxa\Http\Request;
use Myxa\Logging\LogLevel;
use Myxa\Logging\LoggerInterface;
use Myxa\RateLimit\Exceptions\TooManyRequestsException;
use Myxa\RateLimit\RateLimitResult;
use Myxa\Routing\Exceptions\RouteNotFoundException;
use Myxa\Support\Html\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ExceptionHandler::class)]
final class ExceptionHandlerTest extends TestCase
{
    public function testReportUsesWarningForClientErrorsAndErrorForServerErrors(): void
    {
        $logger = new ExceptionHandlerTestLogger();
        $handler = $this->makeHandler(logger: $logger);

        $handler->report(new RouteNotFoundException('GET', '/missing'));
        $handler->report(new \RuntimeException('boom'));

        self::assertCount(2, $logger->entries);
        self::assertSame(LogLevel::Warning->value, $logger->entries[0]['level']);
        self::assertSame('route_not_found', $logger->entries[0]['context']['type']);
        self::assertSame(LogLevel::Error->value, $logger->entries[1]['level']);
        self::assertSame(500, $logger->entries[1]['context']['status']);
    }

    public function testRenderReturnsHtmlErrorPageForBrowserRequests(): void
    {
        $handler = $this->makeHandler(debug: false);
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/missing',
        ]);

        $response = $handler->render(new RouteNotFoundException('GET', '/missing'), $request);

        self::assertSame(404, $response->statusCode());
        self::assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        self::assertStringContainsString('Page not found', $response->content());
        self::assertStringContainsString('route_not_found', $response->content());
    }

    public function testRenderIncludesDebugPayloadForJsonRequestsWhenDebugIsEnabled(): void
    {
        $handler = $this->makeHandler(debug: true);
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/posts',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $handler->render(new \RuntimeException('Sensitive details'), $request);

        self::assertSame(500, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertStringContainsString('"message":"Sensitive details"', $response->content());
        self::assertStringContainsString('"request":"GET \/api\/posts"', $response->content());
        self::assertStringContainsString('"exception":"RuntimeException"', $response->content());
    }

    public function testRenderHandlesAuthenticationRedirectsAndApiHeaders(): void
    {
        $handler = $this->makeHandler();

        $browserResponse = $handler->render(
            new AuthenticationException('web', '/login'),
            new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/dashboard',
            ]),
        );

        self::assertSame(302, $browserResponse->statusCode());
        self::assertSame('/login', $browserResponse->header('Location'));

        $apiResponse = $handler->render(
            new AuthenticationException('api'),
            new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/me',
                'HTTP_ACCEPT' => 'application/json',
            ]),
        );

        self::assertSame(401, $apiResponse->statusCode());
        self::assertSame('Bearer', $apiResponse->header('WWW-Authenticate'));
    }

    public function testRenderAddsRateLimitHeaders(): void
    {
        $handler = $this->makeHandler();
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/posts',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $handler->render(new TooManyRequestsException(
            new RateLimitResult('api|127.0.0.1|/posts', 2, 1, 0, 42, 9999999999, true),
        ), $request);

        self::assertSame(429, $response->statusCode());
        self::assertSame('42', $response->header('Retry-After'));
        self::assertSame('1', $response->header('X-RateLimit-Limit'));
        self::assertSame('0', $response->header('X-RateLimit-Remaining'));
        self::assertSame('9999999999', $response->header('X-RateLimit-Reset'));
    }

    public function testRenderFallsBackToTextWhenHtmlTemplateRenderingFails(): void
    {
        $brokenViews = sys_get_temp_dir() . '/myxa-html-broken-' . uniqid('', true);
        mkdir($brokenViews, 0777, true);

        try {
            $handler = new ExceptionHandler(
                new Html($brokenViews),
                new ConfigRepository([
                    'app' => [
                        'debug' => false,
                    ],
                ]),
            );

            $response = $handler->render(
                new \RuntimeException('Sensitive details'),
                new Request(server: [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/broken',
                ]),
            );

            self::assertSame(500, $response->statusCode());
            self::assertSame('text/plain; charset=UTF-8', $response->header('Content-Type'));
            self::assertSame('Server Error', $response->content());
        } finally {
            rmdir($brokenViews);
        }
    }

    private function makeHandler(bool $debug = false, ?LoggerInterface $logger = null): ExceptionHandler
    {
        return new ExceptionHandler(
            new Html(resource_path('views')),
            new ConfigRepository([
                'app' => [
                    'debug' => $debug,
                    'name' => 'Myxa Test',
                ],
            ]),
            $logger,
        );
    }
}
