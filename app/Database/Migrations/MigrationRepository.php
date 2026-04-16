<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Myxa\Database\DatabaseManager;
use Myxa\Database\Query\QueryBuilder;
use Myxa\Database\Schema\Blueprint;

final class MigrationRepository
{
    /**
     * Persist and query which migrations have been applied per connection.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly MigrationConfig $config,
    ) {
    }

    /**
     * Create the migration tracking table if it does not exist yet.
     */
    public function ensureExists(?string $connection = null): void
    {
        $schema = $this->database->schema($connection);

        if (in_array($this->config->repositoryTable(), $schema->reverseEngineer()->tables(), true)) {
            return;
        }

        $schema->create($this->config->repositoryTable(), function (Blueprint $table): void {
            $table->id();
            $table->string('migration', 191)->unique();
            $table->string('class', 191);
            $table->integer('batch');
            $table->dateTime('applied_at');
        });
    }

    /**
     * Return the applied migration rows in batch and name order.
     *
     * @return list<array<string, mixed>>
     */
    public function all(?string $connection = null): array
    {
        $this->ensureExists($connection);

        $query = $this->database
            ->query($connection)
            ->select('*')
            ->from($this->config->repositoryTable())
            ->orderBy('batch')
            ->orderBy('migration');

        return $this->database->select($query->toSql(), $query->getBindings(), $connection);
    }

    /**
     * Return only the applied migration names for quick lookup.
     *
     * @return list<string>
     */
    public function appliedNames(?string $connection = null): array
    {
        return array_map(
            static fn (array $row): string => (string) ($row['migration'] ?? ''),
            $this->all($connection),
        );
    }

    /**
     * Calculate the next batch number for the given connection.
     */
    public function nextBatch(?string $connection = null): int
    {
        $maxBatch = 0;

        foreach ($this->all($connection) as $row) {
            $batch = (int) ($row['batch'] ?? 0);
            $maxBatch = max($maxBatch, $batch);
        }

        return $maxBatch + 1;
    }

    /**
     * Record a migration as successfully applied.
     */
    public function log(string $migration, string $class, int $batch, ?string $connection = null): void
    {
        $this->ensureExists($connection);

        $query = $this->database
            ->query($connection)
            ->insertInto($this->config->repositoryTable())
            ->values([
                'migration' => $migration,
                'class' => $class,
                'batch' => $batch,
                'applied_at' => gmdate('Y-m-d H:i:s'),
            ]);

        $this->database->insert($query->toSql(), $query->getBindings(), $connection);
    }

    /**
     * Remove a migration entry after it has been rolled back.
     */
    public function delete(string $migration, ?string $connection = null): void
    {
        $this->ensureExists($connection);

        $query = $this->database
            ->query($connection)
            ->deleteFrom($this->config->repositoryTable())
            ->where('migration', '=', $migration);

        $this->database->delete($query->toSql(), $query->getBindings(), $connection);
    }

    /**
     * Return the applied rows that belong to the latest N batches.
     *
     * @return list<array<string, mixed>>
     */
    public function batchesToRollback(int $steps = 1, ?string $connection = null): array
    {
        $steps = max(1, $steps);
        $rows = array_reverse($this->all($connection));
        $targetBatches = [];

        foreach ($rows as $row) {
            $batch = (int) ($row['batch'] ?? 0);
            if (!in_array($batch, $targetBatches, true)) {
                $targetBatches[] = $batch;
            }

            if (count($targetBatches) >= $steps) {
                break;
            }
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => in_array((int) ($row['batch'] ?? 0), $targetBatches, true),
        ));
    }
}
