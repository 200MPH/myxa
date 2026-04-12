<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Config\ConfigRepository;
use App\Routing\RouteCache;
use Myxa\Application;
use Myxa\Console\Command;

final class RouteCacheCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly ConfigRepository $config,
    ) {
    }

    public function name(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Compile application routes into a cached PHP manifest.';
    }

    protected function handle(): int
    {
        if (!RouteCache::isEnabled($this->config)) {
            $this->warning('Route caching is currently disabled by configuration.')->icon();
        }

        $path = RouteCache::buildFromSource($this->app, $this->config);

        $this->success(sprintf('Route cache generated at %s', $path))->icon();

        return 0;
    }
}
