<?php

declare(strict_types=1);

namespace Test\Unit\Maintenance;

use App\Maintenance\MaintenanceResponseFactory;
use Myxa\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MaintenanceResponseFactory::class)]
final class MaintenanceResponseFactoryTest extends TestCase
{
    public function testFactoryReturnsHtmlResponseForBrowserRequests(): void
    {
        $factory = new MaintenanceResponseFactory(base_path());

        $response = $factory->forRequest(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]), [
            'enabled_at' => '2026-04-15T12:00:00+00:00',
        ]);

        self::assertSame(503, $response->statusCode());
        self::assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('60', $response->header('Retry-After'));
        self::assertStringContainsString('temporarily offline for maintenance', $response->content());
    }

    public function testFactoryReturnsJsonResponseForApiRequests(): void
    {
        $factory = new MaintenanceResponseFactory(base_path());

        $response = $factory->forRequest(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/health',
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(503, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame(
            '{"error":{"type":"maintenance_mode","message":"Service Unavailable","status":503}}',
            $response->content(),
        );
    }

    public function testFactoryFallsBackToPlainTextWhenHtmlTemplateCannotBeRendered(): void
    {
        $factory = new MaintenanceResponseFactory('/definitely-missing-myxa-maintenance-view');

        $response = $factory->forRequest(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]), [
            'enabled_at' => 'not-a-date',
        ]);

        self::assertSame(503, $response->statusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('Service Unavailable', $response->content());
        self::assertSame('60', $response->header('Retry-After'));
    }
}
