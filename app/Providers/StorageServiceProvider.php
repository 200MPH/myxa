<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Database\DatabaseManager;
use Myxa\Storage\Db\DatabaseStorage;
use Myxa\Storage\Local\LocalStorage;
use Myxa\Storage\StorageInterface;
use Myxa\Storage\StorageServiceProvider as FrameworkStorageServiceProvider;
use Myxa\Support\ServiceProvider;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $diskConfigurations = $config->get('storage.disks', []);

        if (!is_array($diskConfigurations) || $diskConfigurations === []) {
            return;
        }

        $disks = [];

        foreach ($diskConfigurations as $alias => $diskConfiguration) {
            if (!is_array($diskConfiguration)) {
                continue;
            }

            $disk = $this->resolveDisk((string) $alias, $diskConfiguration);

            if ($disk === null) {
                continue;
            }

            $disks[(string) $alias] = $disk;
        }

        if ($disks === []) {
            return;
        }

        $defaultDisk = (string) $config->get('storage.default', array_key_first($disks));

        $this->app()->register(new FrameworkStorageServiceProvider($disks, $defaultDisk));
    }

    /**
     * @param array<string, mixed> $diskConfiguration
     * @return StorageInterface|callable(): StorageInterface|null
     */
    private function resolveDisk(string $alias, array $diskConfiguration): StorageInterface|callable|null
    {
        $driver = (string) ($diskConfiguration['driver'] ?? 'local');

        return match ($driver) {
            'local' => $this->makeLocalDisk($alias, $diskConfiguration),
            'database', 'db' => $this->makeDatabaseDisk($alias, $diskConfiguration),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $diskConfiguration
     */
    private function makeLocalDisk(string $alias, array $diskConfiguration): ?StorageInterface
    {
        $root = (string) ($diskConfiguration['root'] ?? '');

        if ($root === '') {
            return null;
        }

        return new LocalStorage($root, $alias);
    }

    /**
     * @param array<string, mixed> $diskConfiguration
     * @return callable(): StorageInterface
     */
    private function makeDatabaseDisk(string $alias, array $diskConfiguration): callable
    {
        $fileTable = (string) ($diskConfiguration['file_table'] ?? 'files');
        $contentTable = (string) ($diskConfiguration['content_table'] ?? 'file_contents');

        return function () use ($alias, $fileTable, $contentTable): StorageInterface {
            return new DatabaseStorage(
                fileTable: $fileTable,
                contentTable: $contentTable,
                manager: $this->app()->make(DatabaseManager::class),
                alias: $alias,
            );
        };
    }
}
