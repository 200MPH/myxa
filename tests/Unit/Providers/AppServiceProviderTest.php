<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Config\ConfigRepository;
use App\Http\ExceptionHandler as AppExceptionHandler;
use App\Providers\AppServiceProvider;
use Myxa\Application;
use Myxa\Http\ExceptionHandlerInterface;
use Myxa\Logging\LogLevel;
use Myxa\Logging\LoggerInterface;
use Myxa\Support\Html\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(AppServiceProvider::class)]
final class AppServiceProviderTest extends TestCase
{
    public function testProviderRegistersHtmlExceptionHandlerAndLoggerBindings(): void
    {
        $logFile = sys_get_temp_dir() . '/myxa-app-log-' . uniqid('', true) . '.log';
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'app' => [
                'debug' => true,
                'name' => 'Myxa Test',
                'log' => [
                    'path' => $logFile,
                ],
            ],
        ]));

        $app->register(AppServiceProvider::class);
        $app->boot();

        $html = $app->make(Html::class);
        $handler = $app->make(ExceptionHandlerInterface::class);
        $logger = $app->make(LoggerInterface::class);

        self::assertInstanceOf(Html::class, $html);
        self::assertSame(resource_path('views'), $html->basePath());
        self::assertInstanceOf(AppExceptionHandler::class, $handler);
        self::assertSame($handler, $app->make('exception.handler'));

        $logger->log(LogLevel::Info, 'provider ready');

        self::assertFileExists($logFile);
        self::assertStringContainsString('provider ready', (string) file_get_contents($logFile));

        unlink($logFile);
    }

    public function testProviderBootSetsConfiguredTimezone(): void
    {
        $previousTimezone = date_default_timezone_get();
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository([
            'app' => [
                'timezone' => 'Europe/Warsaw',
            ],
        ]));

        try {
            $app->register(AppServiceProvider::class);
            $app->boot();

            self::assertSame('Europe/Warsaw', date_default_timezone_get());
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }
}
