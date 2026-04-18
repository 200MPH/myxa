<?php

declare(strict_types=1);

namespace Test\Unit\Foundation;

use Myxa\Support\ServiceProvider;

final class ApplicationFactoryTestProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->instance('factory.registered', true);
    }

    public function boot(): void
    {
        $this->app()->instance('factory.booted', true);
    }
}
