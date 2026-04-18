<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MigrateCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run pending migrations and auto-create the migration repository table when needed.';
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Restrict migrations to one connection alias.', true),
        ];
    }

    protected function handle(): int
    {
        $applied = $this->migrations->migrate($this->stringOption('connection'));

        if ($applied === []) {
            $this->info('No pending migrations.')->icon();

            return 0;
        }

        $this->table(
            ['Migration', 'Class', 'Connection'],
            array_map(
                static fn (array $row): array => [$row['migration'], $row['class'], $row['connection']],
                $applied,
            ),
        );

        $this->success(sprintf('Applied %d migration(s).', count($applied)))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
