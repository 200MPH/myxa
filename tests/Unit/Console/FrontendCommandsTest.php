<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\FrontendInstallCommand;
use App\Frontend\FrontendInstallService;
use Myxa\Console\CommandRunner;
use Myxa\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(FrontendInstallCommand::class)]
#[CoversClass(FrontendInstallService::class)]
final class FrontendCommandsTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-frontend-install-' . uniqid('', true);

        mkdir($this->rootPath . '/resources/views/layouts', 0777, true);
        file_put_contents($this->rootPath . '/resources/views/layouts/app.php', <<<'PHP'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body>
<?= $body ?>
</body>
</html>
PHP);
        file_put_contents($this->rootPath . '/.gitignore', ".env\nvendor\n");
        file_put_contents($this->rootPath . '/package.json', <<<'JSON'
{
    "scripts": {
        "lint": "echo lint"
    }
}
JSON);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testFrontendInstallCommandScaffoldsVueHybridToolchain(): void
    {
        $command = new FrontendInstallCommand(new FrontendInstallService($this->rootPath));

        self::assertSame('frontend:install', $command->name());
        self::assertSame(
            'Scaffold a hybrid frontend toolchain. Vue is currently supported.',
            $command->description(),
        );
        self::assertSame('stack', $command->parameters()[0]->name());
        self::assertSame('force', $command->options()[0]->name());

        [$exitCode, $output] = $this->runCommand($command, 'frontend:install', ['stack' => 'vue']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Frontend install complete.', $output);
        self::assertFileExists($this->rootPath . '/vite.config.mjs');
        self::assertFileExists($this->rootPath . '/resources/frontend/app.js');
        self::assertFileExists($this->rootPath . '/resources/frontend/components/CounterWidget.vue');
        self::assertFileExists($this->rootPath . '/public/assets/frontend/.gitignore');

        $packageJson = (string) file_get_contents($this->rootPath . '/package.json');
        self::assertStringContainsString('"frontend:build": "vite build"', $packageJson);
        self::assertStringContainsString('"frontend:watch": "vite build --watch"', $packageJson);
        self::assertStringContainsString('"lint": "echo lint"', $packageJson);
        self::assertStringContainsString('"vue": "^3.5.13"', $packageJson);

        $layout = (string) file_get_contents($this->rootPath . '/resources/views/layouts/app.php');
        self::assertStringContainsString('/assets/frontend/app.js', $layout);

        $gitignore = (string) file_get_contents($this->rootPath . '/.gitignore');
        self::assertStringContainsString('node_modules', $gitignore);
        self::assertStringContainsString('public/assets/frontend/*', $gitignore);
    }

    public function testFrontendInstallCommandRejectsUnsupportedStack(): void
    {
        $command = new FrontendInstallCommand(new FrontendInstallService($this->rootPath));

        [$exitCode, $output] = $this->runCommand($command, 'frontend:install', ['stack' => 'react']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unsupported frontend stack [react].', $output);
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @param array<string, scalar|bool|null> $options
     * @return array{0: int, 1: string}
     */
    private function runCommand(
        FrontendInstallCommand $command,
        string $name,
        array $parameters = [],
        array $options = [],
    ): array {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $runner = new CommandRunner(new Container(), output: $stream);
        $runner->register($command);
        $exitCode = $runner->run($this->argv($name, $parameters, $options));

        rewind($stream);
        $output = (string) stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, $output];
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @param array<string, scalar|bool|null> $options
     * @return list<string>
     */
    private function argv(string $name, array $parameters, array $options): array
    {
        $argv = ['myxa', $name];

        foreach ($parameters as $value) {
            $argv[] = (string) $value;
        }

        foreach ($options as $option => $value) {
            if ($value === true) {
                $argv[] = '--' . $option;

                continue;
            }

            $argv[] = sprintf('--%s=%s', $option, (string) $value);
        }

        return $argv;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
