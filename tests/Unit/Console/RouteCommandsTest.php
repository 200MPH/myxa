<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\RouteClearCommand;
use App\Foundation\ApplicationFactory;
use Myxa\Console\CommandInterface;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RouteCacheCommand::class)]
#[CoversClass(RouteClearCommand::class)]
final class RouteCommandsTest extends TestCase
{
    public function testRouteCacheCommandBuildsCompiledManifest(): void
    {
        $this->setEnvironmentValue('APP_ENV', 'production');
        $this->setEnvironmentValue('ROUTE_CACHE', 'true');

        $cachePath = storage_path('cache/framework/routes.php');
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        $app = ApplicationFactory::create(base_path());
        $command = $app->make(RouteCacheCommand::class);

        [$exitCode, $output] = $this->runCommand($command, 'route:cache');

        try {
            self::assertSame(0, $exitCode);
            self::assertStringContainsString('Route cache generated at', $output);
            self::assertFileExists($cachePath);

            $compiled = file_get_contents($cachePath);

            self::assertIsString($compiled);
            self::assertStringContainsString("Route::match(array (\n  0 => 'GET',\n), '/'", $compiled);
            self::assertStringContainsString("App\\\\Http\\\\Controllers\\\\HealthController", $compiled);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }

            if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
                rmdir($cacheDirectory);
            }
        }
    }

    public function testRouteClearCommandDeletesCompiledManifest(): void
    {
        $cachePath = storage_path('cache/framework/routes.php');
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        file_put_contents($cachePath, '<?php return [];');

        $app = ApplicationFactory::create(base_path());
        $command = $app->make(RouteClearCommand::class);

        [$exitCode, $output] = $this->runCommand($command, 'route:clear');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route cache cleared.', $output);
        self::assertFileDoesNotExist($cachePath);

        if (is_dir($cacheDirectory) && (glob($cacheDirectory . '/*') ?: []) === []) {
            rmdir($cacheDirectory);
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(CommandInterface $command, string $name): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput($name, [], []),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }
}
