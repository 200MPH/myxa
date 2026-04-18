<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\ModelScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class MakeModelCommand extends Command
{
    public function __construct(private readonly ModelScaffolder $models)
    {
    }

    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return 'Generate a model class following the project model conventions.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Model class name, for example User or App\\Models\\User.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('table', 'Explicit table name for a blank scaffold.', true),
            new InputOption('from-table', 'Generate the model from a live database table.', true),
            new InputOption('from-migration', 'Generate the model from a migration file.', true),
            new InputOption('connection', 'Connection alias for reverse-engineered sources.', true),
        ];
    }

    protected function handle(): int
    {
        $path = $this->models->make(
            (string) $this->parameter('name'),
            $this->stringOption('table'),
            $this->stringOption('from-table'),
            $this->stringOption('from-migration'),
            $this->stringOption('connection'),
        );

        $this->success(sprintf('Model created at %s', $path))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
