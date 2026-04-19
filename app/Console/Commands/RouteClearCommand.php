<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Config\ConfigRepository;
use App\Console\Exceptions\CommandFailedException;
use App\Routing\RouteCache;
use Myxa\Console\Command;

final class RouteClearCommand extends Command
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function name(): string
    {
        return 'route:clear';
    }

    public function description(): string
    {
        return 'Delete the compiled route cache manifest.';
    }

    protected function handle(): int
    {
        if (!RouteCache::exists($this->config)) {
            $this->info('Route cache is already clear.')->icon();

            return 0;
        }

        if (!RouteCache::clear($this->config)) {
            throw new CommandFailedException('Unable to remove the route cache file.');
        }

        $this->success('Route cache cleared.')->icon();

        return 0;
    }
}
