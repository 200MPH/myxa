<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MakeMigrationCommand extends Command
{
    public function __construct(private readonly MigrationScaffolder $scaffolder)
    {
    }

    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return 'Create a new forward migration file.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Migration name, for example create_users_table.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('create', 'Generate a create-table migration for the given table.', true),
            new InputOption('table', 'Generate an alter-table migration for the given table.', true),
            new InputOption('class', 'Explicit migration class name.', true),
            new InputOption('connection', 'Optional target connection alias.', true),
        ];
    }

    protected function handle(): int
    {
        $path = $this->scaffolder->make(
            (string) $this->parameter('name'),
            $this->stringOption('create'),
            $this->stringOption('table'),
            $this->stringOption('class'),
            $this->stringOption('connection'),
        );

        $this->success(sprintf('Migration created at %s', $path))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
