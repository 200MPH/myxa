<?php

declare(strict_types=1);

namespace Test\Unit;

use RuntimeException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Test\TestCase;

#[CoversNothing]
final class HelpersTest extends TestCase
{
    public function testPathHelpersResolveAgainstProjectBasePath(): void
    {
        $basePath = dirname(__DIR__, 2);

        self::assertSame($basePath, base_path());
        self::assertSame($basePath . '/app', app_path());
        self::assertSame($basePath . '/config/app.php', config_path('app.php'));
        self::assertSame($basePath . '/database', database_path());
        self::assertSame($basePath . '/database/migrations', database_path('migrations'));
        self::assertSame($basePath . '/public', public_path());
        self::assertSame($basePath . '/resources/views', resource_path('views'));
        self::assertSame($basePath . '/routes/web.php', route_path('web.php'));
        self::assertSame($basePath . '/storage', storage_path());
    }

    public function testEnvHelperNormalizesCommonScalarValues(): void
    {
        $this->setEnvironmentValue('TEST_TRUE', 'true');
        $this->setEnvironmentValue('TEST_FALSE', '(false)');
        $this->setEnvironmentValue('TEST_NULL', 'null');
        $this->setEnvironmentValue('TEST_EMPTY', 'empty');
        $this->setEnvironmentValue('TEST_STRING', 'myxa');

        self::assertTrue(env('TEST_TRUE'));
        self::assertFalse(env('TEST_FALSE'));
        self::assertNull(env('TEST_NULL'));
        self::assertSame('', env('TEST_EMPTY'));
        self::assertSame('myxa', env('TEST_STRING'));
        self::assertSame('fallback', env('UNKNOWN_VALUE', 'fallback'));
    }

    public function testJsonRequestDetectionSupportsAcceptAjaxAndApiPaths(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['REQUEST_URI'] = '/';
        self::assertTrue(myxa_request_expects_json());

        $_SERVER = [];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        self::assertTrue(myxa_request_expects_json());

        $_SERVER = [];
        $_SERVER['REQUEST_URI'] = '/api/health';
        self::assertTrue(myxa_request_expects_json());

        $_SERVER = [];
        $_SERVER['REQUEST_URI'] = '/';
        self::assertFalse(myxa_request_expects_json());
    }

    public function testEmergencyLoggerWritesPrimaryAndSecondaryFailures(): void
    {
        $logFile = sys_get_temp_dir() . '/myxa-error-log-' . uniqid('', true) . '.log';
        $previous = ini_set('error_log', $logFile);

        try {
            myxa_emergency_log(
                new RuntimeException('primary failure'),
                new RuntimeException('secondary failure'),
            );

            $contents = file_get_contents($logFile);

            self::assertIsString($contents);
            self::assertStringContainsString('[myxa emergency]', $contents);
            self::assertStringContainsString('primary failure', $contents);
            self::assertStringContainsString('secondary failure', $contents);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);

            if (is_file($logFile)) {
                unlink($logFile);
            }
        }
    }

    public function testEmergencyResponseCanEmitJsonAndTextPayloads(): void
    {
        http_response_code(200);
        ob_start();
        myxa_emit_emergency_response(503, true);
        $json = (string) ob_get_clean();

        self::assertSame('{"error":{"type":"server_error","message":"Server Error","status":503}}', $json);

        http_response_code(200);
        ob_start();
        myxa_emit_emergency_response(500, false);
        $text = (string) ob_get_clean();

        self::assertSame('Server Error', $text);
    }

    public function testConsoleHintExplainsMissingContainerEntriesGenerically(): void
    {
        self::assertSame(
            'Ensure the service provider responsible for [App\\Queue\\InspectableQueueInterface] is registered in config/app.php. If that feature is intentionally disabled, avoid bootstrapping commands that depend on it.',
            myxa_console_hint_for('Container entry [App\Queue\InspectableQueueInterface] was not found.'),
        );
        self::assertNull(myxa_console_hint_for('Something else failed.'));
    }
}
