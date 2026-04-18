<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use App\Routing\RouteCache;
use Myxa\Support\ServiceProvider;

final class RoutesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $config = $this->app()->make(ConfigRepository::class);

        if (RouteCache::isEnabled($config) && RouteCache::loadCachedRoutes($config)) {
            return;
        }

        RouteCache::loadSourceRoutes();
    }
}
