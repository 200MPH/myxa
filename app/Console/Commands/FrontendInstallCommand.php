<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Frontend\FrontendInstallService;
use App\Frontend\FrontendPackageInstaller;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;
use RuntimeException;

final class FrontendInstallCommand extends Command
{
    private readonly FrontendPackageInstaller $packages;

    public function __construct(
        private readonly FrontendInstallService $frontend,
        ?FrontendPackageInstaller $packages = null,
    ) {
        $this->packages = $packages ?? new FrontendPackageInstaller();
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
            new InputOption(
                'npm',
                'Run npm install after scaffolding. Uses native npm or a temporary Docker Node container.',
            ),
        ];
    }

    protected function handle(): int
    {
        $result = $this->frontend->install(
            (string) $this->parameter('stack', 'vue'),
            $this->booleanOption('force'),
        );

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

        if ($this->booleanOption('npm')) {
            $this->info('Installing npm packages...')->icon();

            try {
                $npm = $this->packages->install(
                    fn (string $line): null => $this->writeProcessLine($line),
                );
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage())->icon();

                return 1;
            }

            $this->success(sprintf('npm packages installed with %s.', $npm['strategy']))->icon();
            $this->success('Frontend install complete. Run `npm run frontend:build` next.')->icon();
        } else {
            $this->success(
                'Frontend install complete. Run `npm install` and `npm run frontend:build` next.',
            )->icon();
        }

        $this->info('Use `npm run frontend:watch` for iterative hybrid frontend work.')->icon();

        return 0;
    }

    private function booleanOption(string $name): bool
    {
        return $this->option($name, false) === true;
    }

    private function writeProcessLine(string $line): null
    {
        $this->output($line)->send();

        return null;
    }
}
