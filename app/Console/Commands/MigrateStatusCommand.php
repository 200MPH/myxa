<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MigrateStatusCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show migration file status, batches, and effective connections.';
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Restrict status to one connection alias.', true),
        ];
    }

    protected function handle(): int
    {
        $rows = $this->migrations->status($this->stringOption('connection'));

        if ($rows === []) {
            $this->info('No migration files were found.')->icon();

            return 0;
        }

        $this->table(
            ['Migration', 'Status', 'Batch', 'Connection'],
            array_map(
                static fn (array $row): array => [
                    $row['migration'],
                    $row['status'],
                    $row['batch'] === null ? '-' : (string) $row['batch'],
                    $row['connection'],
                ],
                $rows,
            ),
        );

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
