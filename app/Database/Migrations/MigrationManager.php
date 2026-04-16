<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Myxa\Database\DatabaseManager;
use Myxa\Database\Migrations\Migration;
use Myxa\Database\Schema\Diff\TableDiff;
use RuntimeException;

final class MigrationManager
{
    /**
     * Coordinate migration execution, rollback, diffing, snapshots, and reverse engineering.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly MigrationConfig $config,
        private readonly MigrationRepository $repository,
        private readonly MigrationLoader $loader,
        private readonly MigrationScaffolder $scaffolder,
    ) {
    }

    /**
     * Run every pending migration and record each successful application.
     *
     * @return list<array{migration: string, class: string, connection: string}>
     */
    public function migrate(?string $connection = null): array
    {
        $migrations = $this->loader->loadAll();
        $applied = [];
        $byConnection = [];
        $batches = [];

        foreach ($migrations as $migration) {
            $effectiveConnection = $this->effectiveConnection($migration->instance, $connection);

            if ($connection !== null && $effectiveConnection !== $connection) {
                continue;
            }

            $byConnection[$effectiveConnection] ??= array_flip($this->repository->appliedNames($effectiveConnection));
            $batches[$effectiveConnection] ??= $this->repository->nextBatch($effectiveConnection);

            if (isset($byConnection[$effectiveConnection][$migration->name])) {
                continue;
            }

            $this->runUp($migration, $effectiveConnection, $batches[$effectiveConnection]);
            $byConnection[$effectiveConnection][$migration->name] = true;

            $applied[] = [
                'migration' => $migration->name,
                'class' => $migration->class,
                'connection' => $effectiveConnection,
            ];
        }

        return $applied;
    }

    /**
     * Roll back the most recent migration batches for one connection.
     *
     * @return list<array{migration: string, class: string, connection: string}>
     */
    public function rollback(int $steps = 1, ?string $connection = null): array
    {
        $connection ??= $this->config->defaultConnection();
        $rows = $this->repository->batchesToRollback($steps, $connection);
        $loaded = [];

        foreach ($this->loader->loadAll() as $migration) {
            $loaded[$migration->name] = $migration;
        }

        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) ($row['migration'] ?? '');
            $migration = $loaded[$name] ?? null;

            if (!$migration instanceof LoadedMigration) {
                throw new RuntimeException(sprintf(
                    'Applied migration [%s] could not be found in %s.',
                    $name,
                    $this->config->migrationsPath(),
                ));
            }

            $this->runDown($migration, $connection);

            $rolledBack[] = [
                'migration' => $migration->name,
                'class' => $migration->class,
                'connection' => $connection,
            ];
        }

        return $rolledBack;
    }

    /**
     * Report whether each migration file has already run and in which batch.
     *
     * @return list<array{migration: string, class: string, connection: string, batch: int|null, status: string}>
     */
    public function status(?string $connection = null): array
    {
        $known = [];
        $rows = $this->repository->all($connection);

        foreach ($rows as $row) {
            $known[(string) $row['migration']] = $row;
        }

        $status = [];

        foreach ($this->loader->loadAll() as $migration) {
            $effectiveConnection = $this->effectiveConnection($migration->instance, $connection);

            if ($connection !== null && $effectiveConnection !== $connection) {
                continue;
            }

            $row = $known[$migration->name] ?? null;

            $status[] = [
                'migration' => $migration->name,
                'class' => $migration->class,
                'connection' => $effectiveConnection,
                'batch' => is_array($row) ? (int) ($row['batch'] ?? 0) : null,
                'status' => is_array($row) ? 'ran' : 'pending',
            ];
        }

        return $status;
    }

    /**
     * Capture the live schema into a JSON snapshot file for later diffing.
     */
    public function snapshot(?string $connection = null, ?string $path = null): string
    {
        $connection ??= $this->config->defaultConnection();
        $path ??= $this->config->snapshotPath($connection);
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create schema snapshot directory [%s].', $directory));
        }

        $snapshot = $this->database->schema($connection)->reverseEngineer()->snapshot()->toJson();

        if (@file_put_contents($path, $snapshot) === false) {
            throw new RuntimeException(sprintf('Unable to write schema snapshot [%s].', $path));
        }

        return $path;
    }

    /**
     * Compare a stored schema snapshot with the current live table and generate alter-migration source.
     *
     * @return array{0: TableDiff, 1: string}
     */
    public function diff(string $table, ?string $connection = null, ?string $snapshotPath = null, ?string $className = null): array
    {
        $connection ??= $this->config->defaultConnection();
        $snapshotPath ??= $this->config->snapshotPath($connection);

        $json = @file_get_contents($snapshotPath);
        if (!is_string($json) || trim($json) === '') {
            throw new RuntimeException(sprintf(
                'Schema snapshot [%s] was not found. Run migrate:snapshot first.',
                $snapshotPath,
            ));
        }

        $reverse = $this->database->schema($connection)->reverseEngineer();
        $stored = $reverse->snapshotFromJson($json)->table($table);
        $live = $reverse->table($table);
        $diff = $reverse->diff($stored, $live);
        $source = $reverse->alterMigration($stored, $live, $className);

        return [$diff, $source];
    }

    /**
     * Generate a baseline create migration by reverse engineering an existing live table.
     */
    public function reverse(string $table, ?string $connection = null, ?string $className = null): string
    {
        $connection ??= $this->config->defaultConnection();
        $source = $this->database->schema($connection)->reverseEngineer()->migration($table, $className);

        return $this->scaffolder->write('create_' . $table . '_table', $source);
    }

    /**
     * Write the diff-generated alter migration directly to the migrations directory.
     */
    public function writeDiffMigration(
        string $table,
        ?string $connection = null,
        ?string $snapshotPath = null,
        ?string $className = null,
    ): string {
        [, $source] = $this->diff($table, $connection, $snapshotPath, $className);

        return $this->scaffolder->write('alter_' . $table . '_table', $source);
    }

    private function runUp(LoadedMigration $loadedMigration, string $connection, int $batch): void
    {
        $migration = $loadedMigration->instance;
        $schema = $this->database->schema($connection);
        $this->repository->ensureExists($connection);

        if ($migration->withinTransaction()) {
            $this->database->transaction(function () use ($migration, $schema, $loadedMigration, $batch, $connection): void {
                $migration->up($schema);
                $this->repository->log($loadedMigration->name, $loadedMigration->class, $batch, $connection);
            }, $connection);

            return;
        }

        $migration->up($schema);
        $this->repository->log($loadedMigration->name, $loadedMigration->class, $batch, $connection);
    }

    private function runDown(LoadedMigration $loadedMigration, string $connection): void
    {
        $migration = $loadedMigration->instance;
        $schema = $this->database->schema($connection);

        if ($migration->withinTransaction()) {
            $this->database->transaction(function () use ($migration, $schema, $loadedMigration, $connection): void {
                $migration->down($schema);
                $this->repository->delete($loadedMigration->name, $connection);
            }, $connection);

            return;
        }

        $migration->down($schema);
        $this->repository->delete($loadedMigration->name, $connection);
    }

    private function effectiveConnection(Migration $migration, ?string $overrideConnection = null): string
    {
        return $migration->connectionName()
            ?? $overrideConnection
            ?? $this->config->defaultConnection();
    }
}
