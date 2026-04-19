<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Exceptions\CommandFailedException;
use Myxa\Cache\CacheManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;
use Throwable;

final class CacheForgetCommand extends Command
{
    public function __construct(private readonly CacheManager $cache)
    {
    }

    public function name(): string
    {
        return 'cache:forget';
    }

    public function description(): string
    {
        return 'Remove a single key from the configured application cache store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('key', 'Cache key to remove, for example users:123 or dashboard:stats.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('store', 'Optional cache store alias to target instead of the default store.', true),
        ];
    }

    protected function handle(): int
    {
        $key = trim((string) $this->parameter('key'));
        $store = $this->stringOption('store');
        $resolvedStore = $store ?? $this->cache->getDefaultStore();

        try {
            if (!$this->cache->forget($key, $store)) {
                $this->info(sprintf('Cache key [%s] was already absent from store [%s].', $key, $resolvedStore))
                    ->icon();

                return 0;
            }
        } catch (Throwable $exception) {
            throw new CommandFailedException(sprintf(
                'Unable to forget cache key [%s] from store [%s]: %s',
                $key,
                $resolvedStore,
                $exception->getMessage(),
            ), previous: $exception);
        }

        $this->success(sprintf('Cache key [%s] forgotten from store [%s].', $key, $resolvedStore))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
