<?php

declare(strict_types=1);

namespace Test\Feature;

use App\Console\Kernel as ConsoleKernel;
use App\Http\Kernel as HttpKernel;
use Myxa\Http\Request;
use PHPUnit\Framework\Attributes\CoversNothing;
use Test\TestCase;

#[CoversNothing]
final class ApplicationBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupMaintenanceState();
    }

    protected function tearDown(): void
    {
        $this->cleanupMaintenanceState();

        parent::tearDown();
    }

    public function testHttpBootstrapReturnsKernelAndServesConfiguredRoutes(): void
    {
        $kernel = require base_path('bootstrap/http.php');

        self::assertInstanceOf(HttpKernel::class, $kernel);

        $healthResponse = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/health',
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(200, $healthResponse->statusCode());
        self::assertSame('application/json; charset=UTF-8', $healthResponse->header('Content-Type'));
        self::assertStringContainsString('"ok":true', $healthResponse->content());
        self::assertStringContainsString('"path":"\/health"', $healthResponse->content());
        self::assertStringContainsString('"version":"', $healthResponse->content());
        self::assertStringContainsString('"version_source":"', $healthResponse->content());

        $homeResponse = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]));

        self::assertSame(200, $homeResponse->statusCode());
        self::assertSame('text/html; charset=UTF-8', $homeResponse->header('Content-Type'));
        self::assertStringContainsString('Myxa App is running :-)', $homeResponse->content());
        self::assertStringContainsString('Documentation', $homeResponse->content());
        self::assertStringContainsString('Version:', $homeResponse->content());
        self::assertStringContainsString('Health endpoint', $homeResponse->content());

        $docsResponse = $kernel->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/docs',
        ]));

        self::assertSame(200, $docsResponse->statusCode());
        self::assertSame('text/html; charset=UTF-8', $docsResponse->header('Content-Type'));
        self::assertStringContainsString('Documentation', $docsResponse->content());
        self::assertStringContainsString('Getting Started', $docsResponse->content());
    }

    public function testConsoleBootstrapReturnsConsoleKernel(): void
    {
        $kernel = require base_path('bootstrap/console.php');

        self::assertInstanceOf(ConsoleKernel::class, $kernel);
    }

    private function cleanupMaintenanceState(): void
    {
        $markerPath = base_path('maintenance.json');
        if (is_file($markerPath)) {
            unlink($markerPath);
        }

        $activityPath = storage_path('maintenance/console-activity.json');
        if (is_file($activityPath)) {
            unlink($activityPath);
        }

        $directory = dirname($activityPath);
        if (is_dir($directory) && (glob($directory . '/*') ?: []) === []) {
            rmdir($directory);
        }
    }
}
