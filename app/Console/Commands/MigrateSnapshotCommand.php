<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MigrateSnapshotCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate:snapshot';
    }

    public function description(): string
    {
        return 'Capture a schema snapshot JSON file for later diffing.';
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Connection alias to snapshot.', true),
            new InputOption('path', 'Optional destination path for the snapshot JSON.', true),
        ];
    }

    protected function handle(): int
    {
        $path = $this->migrations->snapshot(
            $this->stringOption('connection'),
            $this->stringOption('path'),
        );

        $this->success(sprintf('Schema snapshot written to %s', $path))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
