<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Application;
use App\Http\ExceptionHandler as AppExceptionHandler;
use Myxa\Http\ExceptionHandlerInterface;
use Myxa\Logging\FileLogger;
use Myxa\Logging\LoggerInterface;
use Myxa\Support\Html\Html;
use Myxa\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->singleton(
            Html::class,
            static fn (): Html => new Html(resource_path('views')),
        );

        $this->app()->singleton(
            ExceptionHandlerInterface::class,
            static fn (Application $app): ExceptionHandlerInterface => $app->make(AppExceptionHandler::class),
        );
        $this->app()->singleton(
            'exception.handler',
            static fn (Application $app): ExceptionHandlerInterface => $app->make(ExceptionHandlerInterface::class),
        );

        $this->app()->singleton(
            LoggerInterface::class,
            static function (Application $app): LoggerInterface {
                $config = $app->make(ConfigRepository::class);
                $path = (string) $config->get('app.log.path', storage_path('data/logs/app.log'));

                return new FileLogger($path);
            },
        );
    }

    public function boot(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $timezone = (string) $config->get('app.timezone', 'UTC');

        date_default_timezone_set($timezone);
    }
}
