<?php

declare(strict_types=1);

namespace App\Providers;

use Myxa\Support\ServiceProvider;

final class RoutesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $routeFiles = glob(route_path('*.php')) ?: [];
        sort($routeFiles);

        foreach ($routeFiles as $routeFile) {
            if (!is_file($routeFile)) {
                continue;
            }

            require $routeFile;
        }
    }
}
