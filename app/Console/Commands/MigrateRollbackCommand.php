<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MigrateRollbackCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Roll back the latest migration batch or batches.';
    }

    public function options(): array
    {
        return [
            new InputOption('step', 'How many batches to roll back.', true, false, 1),
            new InputOption('connection', 'Target one connection alias.', true),
        ];
    }

    protected function handle(): int
    {
        $rolledBack = $this->migrations->rollback(
            $this->intOption('step', 1),
            $this->stringOption('connection'),
        );

        if ($rolledBack === []) {
            $this->info('Nothing to roll back.')->icon();

            return 0;
        }

        $this->table(
            ['Migration', 'Class', 'Connection'],
            array_map(
                static fn (array $row): array => [$row['migration'], $row['class'], $row['connection']],
                $rolledBack,
            ),
        );

        $this->success(sprintf('Rolled back %d migration(s).', count($rolledBack)))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function intOption(string $name, int $default): int
    {
        $value = $this->option($name, $default);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
