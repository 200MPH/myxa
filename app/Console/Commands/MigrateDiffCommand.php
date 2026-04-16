<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MigrateDiffCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate:diff';
    }

    public function description(): string
    {
        return 'Compare a stored schema snapshot to a live table and optionally write an alter migration.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('table', 'Table name to compare against the stored snapshot.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Connection alias to inspect.', true),
            new InputOption('snapshot', 'Override the schema snapshot JSON path.', true),
            new InputOption('class', 'Optional alter migration class name.', true),
            new InputOption('write', 'Write the generated alter migration to the migrations directory.'),
        ];
    }

    protected function handle(): int
    {
        $table = (string) $this->parameter('table');
        [$diff] = $this->migrations->diff(
            $table,
            $this->stringOption('connection'),
            $this->stringOption('snapshot'),
            $this->stringOption('class'),
        );

        $this->table(
            ['Table', 'Has Changes', 'Added', 'Dropped', 'Changed Indexes/FKs'],
            [[
                $diff->table(),
                $diff->hasChanges() ? 'yes' : 'no',
                (string) count($diff->addedColumns()),
                (string) count($diff->droppedColumns()),
                (string) (
                    count($diff->addedIndexes())
                    + count($diff->droppedIndexes())
                    + count($diff->addedForeignKeys())
                    + count($diff->droppedForeignKeys())
                ),
            ]],
        );

        if (!$diff->hasChanges()) {
            $this->info('No schema changes were detected.')->icon();

            return 0;
        }

        if ($this->option('write', false)) {
            $path = $this->migrations->writeDiffMigration(
                $table,
                $this->stringOption('connection'),
                $this->stringOption('snapshot'),
                $this->stringOption('class'),
            );

            $this->success(sprintf('Alter migration written to %s', $path))->icon();
        } else {
            $this->info('Use --write to generate the alter migration file.')->icon();
        }

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
