<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Frontend\FrontendInstallService;
use InvalidArgumentException;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class FrontendInstallCommand extends Command
{
    public function __construct(private readonly FrontendInstallService $frontend)
    {
    }

    public function name(): string
    {
        return 'frontend:install';
    }

    public function description(): string
    {
        return 'Scaffold a hybrid frontend toolchain. Vue is currently supported.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument(
                'stack',
                'Frontend stack to scaffold. Vue is currently the supported option.',
                false,
                'vue',
            ),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('force', 'Overwrite managed scaffold files when they already exist.'),
        ];
    }

    protected function handle(): int
    {
        try {
            $result = $this->frontend->install(
                (string) $this->parameter('stack', 'vue'),
                $this->booleanOption('force'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage())->icon();

            return 1;
        }

        if ($result['created'] !== []) {
            $this->table(
                ['Created'],
                array_map(static fn (string $path): array => [$path], $result['created']),
            );
        }

        if ($result['updated'] !== []) {
            $this->table(
                ['Updated'],
                array_map(static fn (string $path): array => [$path], $result['updated']),
            );
        }

        if ($result['skipped'] !== []) {
            $this->table(
                ['Skipped'],
                array_map(static fn (string $path): array => [$path], $result['skipped']),
            );
        }

        foreach ($result['warnings'] as $warning) {
            $this->warning($warning)->icon();
        }

        $this->success(
            'Frontend install complete. Run `npm install` and `npm run frontend:build` next.',
        )->icon();
        $this->info('Use `npm run frontend:watch` for iterative hybrid frontend work.')->icon();

        return 0;
    }

    private function booleanOption(string $name): bool
    {
        return $this->option($name, false) === true;
    }
}
