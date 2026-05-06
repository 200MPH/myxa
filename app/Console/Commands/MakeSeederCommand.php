<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\SeederScaffolder;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class MakeSeederCommand extends Command
{
    public function __construct(private readonly SeederScaffolder $scaffolder)
    {
    }

    public function name(): string
    {
        return 'make:seeder';
    }

    public function description(): string
    {
        return 'Create a new application seeder file.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('name', 'Seeder class name, for example UserSeeder or Demo/UserSeeder.'),
        ];
    }

    protected function handle(): int
    {
        $path = $this->scaffolder->make((string) $this->parameter('name'));

        $this->success(sprintf('Seeder created at %s', $path))->icon();

        return 0;
    }
}
