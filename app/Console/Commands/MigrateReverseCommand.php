<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MigrateReverseCommand extends Command
{
    public function __construct(private readonly MigrationManager $migrations)
    {
    }

    public function name(): string
    {
        return 'migrate:reverse';
    }

    public function description(): string
    {
        return 'Generate a create migration from an existing live table.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('table', 'Live database table to reverse engineer.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Source connection alias.', true),
            new InputOption('class', 'Optional migration class name.', true),
        ];
    }

    protected function handle(): int
    {
        $path = $this->migrations->reverse(
            (string) $this->parameter('table'),
            $this->stringOption('connection'),
            $this->stringOption('class'),
        );

        $this->success(sprintf('Reverse-engineered migration written to %s', $path))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
