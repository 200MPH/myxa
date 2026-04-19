<?php

declare(strict_types=1);

namespace Test\Unit\Console {

    use App\Console\Commands\MaintenanceOffCommand;
    use App\Console\Commands\RouteClearCommand;
    use App\Config\ConfigRepository;
    use App\Maintenance\MaintenanceMode;
    use Myxa\Console\CommandRunner;
    use Myxa\Container\Container;
    use PHPUnit\Framework\Attributes\CoversClass;
    use Test\TestCase;

    #[CoversClass(MaintenanceOffCommand::class)]
    #[CoversClass(RouteClearCommand::class)]
    #[CoversClass(MaintenanceMode::class)]
    final class CommandFailureBranchesTest extends TestCase
    {
        private string $basePath;

        protected function setUp(): void
        {
            parent::setUp();

            $this->basePath = sys_get_temp_dir() . '/myxa-command-failures-' . uniqid('', true);
            mkdir($this->basePath, 0777, true);
        }

        protected function tearDown(): void
        {
            unset(
                $GLOBALS['myxa.maintenance.file_put_contents_override'],
                $GLOBALS['myxa.maintenance.json_encode_override'],
                $GLOBALS['myxa.maintenance.flock_override'],
                $GLOBALS['myxa.maintenance.is_dir_override'],
                $GLOBALS['myxa.maintenance.unlink_override'],
                $GLOBALS['myxa.route_cache.unlink_override'],
            );

            $this->removeDirectory($this->basePath);

            parent::tearDown();
        }

        public function testMaintenanceOffCommandFailsWhenMarkerCannotBeRemoved(): void
        {
            $maintenance = new MaintenanceMode($this->basePath);
            $maintenance->enable('phpunit');

            $markerPath = $maintenance->markerPath();
            $GLOBALS['myxa.maintenance.unlink_override'] = static function (string $path) use ($markerPath): bool {
                self::assertSame($markerPath, $path);

                return false;
            };

            [$exitCode, $output] = $this->runCommand(new MaintenanceOffCommand($maintenance), 'maintenance:off');

            self::assertSame(1, $exitCode);
            self::assertStringContainsString('Unable to remove the maintenance marker file.', $output);
            self::assertFalse($maintenance->disable());
            self::assertFileExists($markerPath);
        }

        public function testRouteClearCommandFailsWhenCachedManifestCannotBeRemoved(): void
        {
            $cachePath = $this->basePath . '/routes.php';
            file_put_contents($cachePath, '<?php return [];');

            $config = new ConfigRepository([
                'cache' => [
                    'routes' => [
                        'path' => $cachePath,
                    ],
                ],
            ]);

            $GLOBALS['myxa.route_cache.unlink_override'] = static function (string $path) use ($cachePath): bool {
                self::assertSame($cachePath, $path);

                return false;
            };

            [$exitCode, $output] = $this->runCommand(new RouteClearCommand($config), 'route:clear');

            self::assertSame(1, $exitCode);
            self::assertStringContainsString('Unable to remove the route cache file.', $output);
            self::assertFileExists($cachePath);
        }

        /**
         * @return array{0: int, 1: string}
         */
        private function runCommand(object $command, string $name): array
        {
            $stream = fopen('php://temp', 'w+b');
            self::assertIsResource($stream);

            $runner = new CommandRunner(new Container(), output: $stream);
            $runner->register($command);
            $exitCode = $runner->run(['myxa', $name]);

            rewind($stream);
            $output = (string) stream_get_contents($stream);
            fclose($stream);

            return [$exitCode, $output];
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

                @unlink($child);
            }

            @rmdir($path);
        }
    }
}
