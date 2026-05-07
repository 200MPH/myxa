<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\SeederManager;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class DbSeedCommand extends Command
{
    public function __construct(private readonly SeederManager $seeders)
    {
    }

    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return 'Run the configured database seeder or a specific seeder class.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('seeder', 'Optional seeder class, name, or file path to run.', false),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('connection', 'Default SQL database connection alias for this run.', true),
            new InputOption('redis-connection', 'Default Redis connection alias for this run.', true),
            new InputOption('mongo-connection', 'Default Mongo connection alias for this run.', true),
            new InputOption('truncate', 'Allow the seeder to truncate or reset its target data before seeding.'),
        ];
    }

    protected function handle(): int
    {
        $seeded = $this->seeders->seed(
            $this->stringParameter('seeder'),
            $this->stringOption('connection'),
            $this->stringOption('redis-connection'),
            $this->stringOption('mongo-connection'),
            $this->boolOption('truncate'),
        );

        $this->success(sprintf('Seeded %s.', $seeded['class']))->icon();

        return 0;
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function boolOption(string $name): bool
    {
        return $this->option($name) === true;
    }
}
