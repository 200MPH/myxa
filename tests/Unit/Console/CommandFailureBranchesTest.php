<?php

declare(strict_types=1);

namespace App\Maintenance {

    function file_put_contents(string $filename, mixed $data, int $flags = 0): int|false
    {
        if (isset($GLOBALS['myxa.maintenance.file_put_contents_override'])) {
            return $GLOBALS['myxa.maintenance.file_put_contents_override']($filename, $data, $flags);
        }

        return \file_put_contents($filename, $data, $flags);
    }

    function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        if (isset($GLOBALS['myxa.maintenance.json_encode_override'])) {
            return $GLOBALS['myxa.maintenance.json_encode_override']($value, $flags, $depth);
        }

        return \json_encode($value, $flags, $depth);
    }

    function flock(mixed $stream, int $operation, ?int &$wouldBlock = null): bool
    {
        if (isset($GLOBALS['myxa.maintenance.flock_override'])) {
            return $GLOBALS['myxa.maintenance.flock_override']($stream, $operation, $wouldBlock);
        }

        return \flock($stream, $operation, $wouldBlock);
    }

    function is_dir(string $filename): bool
    {
        if (isset($GLOBALS['myxa.maintenance.is_dir_override'])) {
            return $GLOBALS['myxa.maintenance.is_dir_override']($filename);
        }

        return \is_dir($filename);
    }

    function unlink(string $filename): bool
    {
        if (isset($GLOBALS['myxa.maintenance.unlink_override'])) {
            return $GLOBALS['myxa.maintenance.unlink_override']($filename);
        }

        return \unlink($filename);
    }
}

namespace App\Routing {

    function unlink(string $filename): bool
    {
        if (isset($GLOBALS['myxa.route_cache.unlink_override'])) {
            return $GLOBALS['myxa.route_cache.unlink_override']($filename);
        }

        return \unlink($filename);
    }
}

namespace Test\Unit\Console {

    use App\Console\Commands\MaintenanceOffCommand;
    use App\Console\Commands\RouteClearCommand;
    use App\Config\ConfigRepository;
    use App\Maintenance\MaintenanceMode;
    use Myxa\Console\ConsoleInput;
    use Myxa\Console\ConsoleOutput;
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

            $exitCode = $command->run(
                new ConsoleInput($name, [], []),
                new ConsoleOutput($stream, ansi: false),
            );

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

namespace Test\Unit\Maintenance {

    use App\Maintenance\MaintenanceMode;
    use PHPUnit\Framework\Attributes\CoversClass;
    use Test\TestCase;

    #[CoversClass(MaintenanceMode::class)]
    final class MaintenanceModeFailureBranchesTest extends TestCase
    {
        private string $basePath;

        protected function setUp(): void
        {
            parent::setUp();

            $this->basePath = sys_get_temp_dir() . '/myxa-maintenance-failures-' . uniqid('', true);
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
            );

            $this->removeDirectory($this->basePath);

            parent::tearDown();
        }

        public function testEnableThrowsWhenMarkerCannotBeWritten(): void
        {
            $maintenance = new MaintenanceMode($this->basePath);
            $markerPath = $maintenance->markerPath();

            $GLOBALS['myxa.maintenance.file_put_contents_override'] = static function (
                string $path,
                mixed $data,
                int $flags,
            ) use ($markerPath): false {
                self::assertSame($markerPath, $path);
                self::assertSame(\LOCK_EX, $flags);
                self::assertIsString($data);

                return false;
            };

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to write maintenance marker');

            $maintenance->enable('phpunit');
        }

        public function testEnableThrowsWhenMaintenancePayloadCannotBeEncoded(): void
        {
            $maintenance = new MaintenanceMode($this->basePath);

            $GLOBALS['myxa.maintenance.json_encode_override'] = static function (
                mixed $value,
                int $flags,
                int $depth,
            ): false {
                self::assertIsArray($value);
                self::assertSame(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR, $flags);
                self::assertSame(512, $depth);

                return false;
            };

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to encode maintenance state.');

            $maintenance->enable('phpunit');
        }

        public function testBeginConsoleActivityThrowsWhenMaintenanceStateDirectoryCannotBeCreated(): void
        {
            $invalidBasePath = $this->basePath . '/occupied-base';
            file_put_contents($invalidBasePath, 'not-a-directory');

            $maintenance = new MaintenanceMode($invalidBasePath);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to create maintenance state directory');

            $maintenance->beginConsoleActivity('queue:work');
        }

        public function testPrivateConsoleStateHelpersHandleDirectoryCollisionsAndBasicProcessChecks(): void
        {
            $maintenance = new MaintenanceMode($this->basePath);
            $statePath = $this->basePath . '/storage/maintenance/console-activity.json';
            mkdir($statePath, 0777, true);

            $readState = new \ReflectionMethod(MaintenanceMode::class, 'readConsoleActivityState');
            $readState->setAccessible(true);
            $mutateState = new \ReflectionMethod(MaintenanceMode::class, 'mutateConsoleActivityState');
            $mutateState->setAccessible(true);
            $ensureDirectory = new \ReflectionMethod(MaintenanceMode::class, 'ensureConsoleActivityDirectoryExists');
            $ensureDirectory->setAccessible(true);
            $isProcessRunning = new \ReflectionMethod(MaintenanceMode::class, 'isProcessRunning');
            $isProcessRunning->setAccessible(true);

            try {
                $readState->invoke($maintenance);
                self::fail('Expected readConsoleActivityState() to fail when the state path is a directory.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to open maintenance console state', $exception->getMessage());
            }

            try {
                $mutateState->invoke($maintenance, static fn (array $state): array => $state);
                self::fail('Expected mutateConsoleActivityState() to fail when the state path is a directory.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to open maintenance console state', $exception->getMessage());
            }

            self::assertNull($ensureDirectory->invoke($maintenance));
            self::assertFalse($isProcessRunning->invoke($maintenance, -1));
            self::assertTrue($isProcessRunning->invoke($maintenance, getmypid()));
            self::assertTrue($maintenance->waitForIdleConsole(0, 100));
        }

        public function testPrivateConsoleStateHelpersHandleLockEncodingAndProcBranches(): void
        {
            $maintenance = new MaintenanceMode($this->basePath);
            $readState = new \ReflectionMethod(MaintenanceMode::class, 'readConsoleActivityState');
            $readState->setAccessible(true);
            $mutateState = new \ReflectionMethod(MaintenanceMode::class, 'mutateConsoleActivityState');
            $mutateState->setAccessible(true);
            $writeState = new \ReflectionMethod(MaintenanceMode::class, 'writeConsoleActivityState');
            $writeState->setAccessible(true);
            $isProcessRunning = new \ReflectionMethod(MaintenanceMode::class, 'isProcessRunning');
            $isProcessRunning->setAccessible(true);

            $GLOBALS['myxa.maintenance.flock_override'] = static function (): bool {
                return false;
            };

            try {
                $readState->invoke($maintenance);
                self::fail('Expected readConsoleActivityState() to fail when the state file cannot be locked.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to lock maintenance console state', $exception->getMessage());
            }

            try {
                $mutateState->invoke($maintenance, static fn (array $state): array => $state);
                self::fail('Expected mutateConsoleActivityState() to fail when the state file cannot be locked.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to lock maintenance console state', $exception->getMessage());
            }

            unset($GLOBALS['myxa.maintenance.flock_override']);

            $GLOBALS['myxa.maintenance.json_encode_override'] = static fn (): false => false;
            $handle = fopen('php://temp', 'w+b');
            self::assertIsResource($handle);

            try {
                $writeState->invoke($maintenance, $handle, ['commands' => []]);
                self::fail('Expected writeConsoleActivityState() to fail when encoding the state fails.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Failed to encode maintenance console state.', $exception->getMessage());
            } finally {
                fclose($handle);
            }

            unset($GLOBALS['myxa.maintenance.json_encode_override']);

            $procPid = 424242;
            $procPath = DIRECTORY_SEPARATOR . 'proc' . DIRECTORY_SEPARATOR . $procPid;
            $GLOBALS['myxa.maintenance.is_dir_override'] = static function (string $path) use ($procPath): bool {
                if ($path === $procPath) {
                    return true;
                }

                return \is_dir($path);
            };

            self::assertTrue($isProcessRunning->invoke($maintenance, $procPid));
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
