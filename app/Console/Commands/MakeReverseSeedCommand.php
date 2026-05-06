<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\ReverseSeedScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputOption;

final class MakeReverseSeedCommand extends Command
{
    public function __construct(private readonly ReverseSeedScaffolder $scaffolder)
    {
    }

    public function name(): string
    {
        return 'make:reverse-seed';
    }

    public function description(): string
    {
        return 'Create a seeder from existing relational database rows.';
    }

    public function options(): array
    {
        return [
            new InputOption('limit', 'Maximum rows to read per table.', true),
            new InputOption('tables', 'Comma-separated table list to reverse seed.', true),
            new InputOption('table', 'Root table to reverse seed with directly related tables.', true),
            new InputOption('ignore-relations', 'Comma-separated related tables to skip.', true),
            new InputOption('connection', 'SQL database connection alias to read from.', true),
            new InputOption('class', 'Seeder class name to generate.', true),
            new InputOption('exclude-columns', 'Comma-separated columns to omit.', true),
            new InputOption('mask', 'Comma-separated columns to replace with fake-safe values.', true),
            new InputOption('override', 'Comma-separated column=value replacements.', true),
            new InputOption('password', 'Plain password to hash for password/password_hash columns.', true),
        ];
    }

    protected function handle(): int
    {
        $path = $this->scaffolder->make(
            table: $this->stringOption('table'),
            tables: $this->csvOption('tables'),
            limit: $this->limitOption(),
            connection: $this->stringOption('connection'),
            className: $this->stringOption('class'),
            ignoreRelations: $this->csvOption('ignore-relations'),
            excludeColumns: $this->csvOption('exclude-columns'),
            maskColumns: $this->csvOption('mask'),
            overrides: $this->overrideOption(),
            password: $this->stringOption('password'),
        );

        $this->success(sprintf('Reverse seeder created at %s', $path))->icon();

        return 0;
    }

    private function limitOption(): int
    {
        $value = $this->option('limit', 20);

        return is_numeric($value) ? max(1, (int) $value) : 20;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function csvOption(string $name): array
    {
        $value = $this->stringOption($name);
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * @return array<string, string>
     */
    private function overrideOption(): array
    {
        $overrides = [];

        foreach ($this->csvOption('override') as $entry) {
            [$column, $value] = array_pad(explode('=', $entry, 2), 2, '');
            $column = trim($column);

            if ($column !== '') {
                $overrides[$column] = $value;
            }
        }

        return $overrides;
    }
}
