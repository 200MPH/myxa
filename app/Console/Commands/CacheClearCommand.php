<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Myxa\Cache\CacheManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;
use Throwable;

final class CacheClearCommand extends Command
{
    public function __construct(private readonly CacheManager $cache)
    {
    }

    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Clear the configured application cache store.';
    }

    public function options(): array
    {
        return [
            new InputOption('store', 'Optional cache store alias to clear instead of the default store.', true),
        ];
    }

    protected function handle(): int
    {
        $store = $this->stringOption('store');
        $resolvedStore = $store ?? $this->cache->getDefaultStore();

        try {
            if (!$this->cache->clear($store)) {
                $this->error(sprintf('Unable to clear cache store [%s].', $resolvedStore))->icon();

                return 1;
            }
        } catch (Throwable $exception) {
            $this->error(sprintf('Unable to clear cache store [%s]: %s', $resolvedStore, $exception->getMessage()))
                ->icon();

            return 1;
        }

        $this->success(sprintf('Cache store [%s] cleared.', $resolvedStore))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
