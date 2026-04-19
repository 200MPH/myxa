<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Config\ConfigRepository;
use App\Console\Exceptions\CommandFailedException;
use Myxa\Application;
use Myxa\Console\Command;
use Myxa\Console\InputOption;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\PhpRedisStore;
use Myxa\Redis\RedisManager;
use RuntimeException;

final class RateLimitClearCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly ConfigRepository $config,
    ) {
    }

    public function name(): string
    {
        return 'rate-limit:clear';
    }

    public function description(): string
    {
        return 'Clear persisted rate-limit counters from the configured rate-limit store.';
    }

    public function options(): array
    {
        return [
            new InputOption('store', 'Optional rate-limit store alias to clear instead of the default store.', true),
            new InputOption(
                'prefix',
                'Optional Redis storage prefix to clear. Defaults to the configured Redis prefix.',
                true,
            ),
            new InputOption('force', 'Actually clear the selected rate-limit data after showing the warning.'),
        ];
    }

    protected function handle(): int
    {
        $store = $this->stringOption('store');
        $prefix = $this->stringOption('prefix');
        $target = $this->resolveTarget($store, $prefix);

        if (!$this->boolOption('force')) {
            $this->warning(sprintf(
                'This will clear %s. Re-run with --force to continue.',
                $target['summary'],
            ))->icon();

            return 1;
        }

        $this->warning(sprintf('Clearing %s...', $target['summary']))->icon();

        $cleared = match ($target['driver']) {
            'file' => $this->clearFileStore($target['configuration']),
            'redis' => $this->clearRedisStore($target['configuration'], $target['resolved_prefix']),
            default => throw new RuntimeException(sprintf(
                'Unsupported rate limit store driver [%s] for store [%s].',
                $target['driver'],
                $target['store'],
            )),
        };

        if ($cleared === 0) {
            $this->info(sprintf('No rate-limit entries matched %s.', $target['summary']))->icon();

            return 0;
        }

        $this->success(sprintf(
            'Cleared %d %s from %s.',
            $cleared,
            $cleared === 1 ? 'rate-limit entry' : 'rate-limit entries',
            $target['summary'],
        ))->icon();

        return 0;
    }

    /**
     * @return array{
     *     store: string,
     *     driver: string,
     *     configuration: array<string, mixed>,
     *     resolved_prefix: string|null,
     *     summary: string
     * }
     */
    private function resolveTarget(?string $store, ?string $prefix): array
    {
        $resolvedStore = $store ?? (string) $this->config->get('rate_limit.default_store', 'default');

        try {
            $configuredStore = $store ?? (string) $this->config->get('rate_limit.default_store');
            if ($configuredStore === '') {
                throw new RuntimeException('Rate limit default_store must be configured.');
            }

            $configuration = $this->config->get(sprintf('rate_limit.stores.%s', $configuredStore));

            if (!is_array($configuration)) {
                throw new RuntimeException(sprintf('Rate limit store [%s] is not configured.', $configuredStore));
            }

            $driver = (string) ($configuration['driver'] ?? '');

            return match ($driver) {
                'file' => $this->resolveFileTarget($configuredStore, $configuration, $prefix),
                'redis' => $this->resolveRedisTarget($configuredStore, $configuration, $prefix),
                default => throw new RuntimeException(sprintf(
                    'Unsupported rate limit store driver [%s] for store [%s].',
                    $driver,
                    $configuredStore,
                )),
            };
        } catch (RuntimeException $exception) {
            throw new CommandFailedException(sprintf(
                'Unable to inspect rate-limit store [%s]: %s',
                $resolvedStore,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{
     *     store: string,
     *     driver: string,
     *     configuration: array<string, mixed>,
     *     resolved_prefix: string|null,
     *     summary: string
     * }
     */
    private function resolveFileTarget(string $store, array $configuration, ?string $prefix): array
    {
        if ($prefix !== null) {
            throw new RuntimeException('The --prefix option is only supported for Redis-backed rate-limit stores.');
        }

        $path = trim((string) ($configuration['path'] ?? ''));

        if ($path === '') {
            throw new RuntimeException(sprintf('Rate limit file store [%s] must define a path.', $store));
        }

        return [
            'store' => $store,
            'driver' => 'file',
            'configuration' => $configuration,
            'resolved_prefix' => null,
            'summary' => sprintf('all rate-limit counters in store [%s] at [%s]', $store, $path),
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{
     *     store: string,
     *     driver: string,
     *     configuration: array<string, mixed>,
     *     resolved_prefix: string,
     *     summary: string
     * }
     */
    private function resolveRedisTarget(string $store, array $configuration, ?string $prefix): array
    {
        $connection = trim((string) ($configuration['connection'] ?? ''));

        if ($connection === '') {
            throw new RuntimeException(sprintf('Rate limit Redis store [%s] must define a connection.', $store));
        }

        $resolvedPrefix = $prefix ?? trim((string) ($configuration['prefix'] ?? ''));

        if ($resolvedPrefix === '') {
            throw new RuntimeException(sprintf('Rate limit Redis store [%s] must define a prefix.', $store));
        }

        return [
            'store' => $store,
            'driver' => 'redis',
            'configuration' => $configuration,
            'resolved_prefix' => $resolvedPrefix,
            'summary' => sprintf(
                'rate-limit counters in store [%s] using Redis prefix [%s]',
                $store,
                $resolvedPrefix,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function clearFileStore(array $configuration): int
    {
        $path = (string) $configuration['path'];

        if (!is_dir($path)) {
            return 0;
        }

        $cleared = 0;

        foreach (glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [] as $entry) {
            if (!is_string($entry) || !is_file($entry)) {
                continue;
            }

            if (@unlink($entry)) {
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function clearRedisStore(array $configuration, string $prefix): int
    {
        $connection = (string) $configuration['connection'];

        try {
            $manager = $this->app->make(RedisManager::class);
            $store = $manager->connection($connection)->store();

            if ($store instanceof InMemoryRedisStore) {
                $cleared = 0;

                foreach (array_keys($store->all()) as $key) {
                    if (!str_starts_with($key, $prefix) || !$store->delete($key)) {
                        continue;
                    }

                    $cleared++;
                }

                return $cleared;
            }

            if ($store instanceof PhpRedisStore) {
                $keys = $store->client()->keys($prefix . '*');

                if (!is_array($keys) || $keys === []) {
                    return 0;
                }

                return (int) $store->client()->del($keys);
            }
        } catch (RuntimeException $exception) {
            throw new CommandFailedException(sprintf(
                'Unable to clear rate-limit store [%s]: %s',
                (string) ($configuration['connection'] ?? 'redis'),
                $exception->getMessage(),
            ), previous: $exception);
        }

        throw new CommandFailedException('Redis rate-limit clearing is not supported by this Redis store.');
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function boolOption(string $name): bool
    {
        return (bool) $this->option($name, false);
    }
}
