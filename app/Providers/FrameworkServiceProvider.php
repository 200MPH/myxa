<?php

declare(strict_types=1);

namespace App\Providers;

use Myxa\Http\ExceptionHandlerServiceProvider;
use Myxa\Http\RequestServiceProvider;
use Myxa\Http\ResponseServiceProvider;
use Myxa\Logging\LoggingServiceProvider;
use Myxa\Routing\RouteServiceProvider;
use Myxa\Support\ServiceProvider;
use Myxa\Validation\ValidationServiceProvider;

final class FrameworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->register(LoggingServiceProvider::class);
        $this->app()->register(ExceptionHandlerServiceProvider::class);
        $this->app()->register(RequestServiceProvider::class);
        $this->app()->register(ResponseServiceProvider::class);
        $this->app()->register(RouteServiceProvider::class);
        $this->app()->register(ValidationServiceProvider::class);
    }
}
