<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Config\ConfigRepository;
use App\Console\Commands\StorageLinkCommand;
use Myxa\Console\CommandInterface;
use Myxa\Console\CommandRunner;
use Myxa\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(StorageLinkCommand::class)]
final class StorageCommandsTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-storage-command-' . uniqid('', true);

        mkdir($this->rootPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testStorageLinkCommandCreatesPublicSymlink(): void
    {
        $publicPath = $this->rootPath . '/public';
        $storageRoot = $this->rootPath . '/storage/app/public';

        mkdir($publicPath, 0777, true);
        mkdir($storageRoot, 0777, true);

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => $storageRoot,
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');
        $link = $publicPath . '/storage';

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Public storage link created at', $output);
        self::assertTrue(is_link($link));
        self::assertSame(realpath($storageRoot), realpath($link) ?: null);
    }

    public function testStorageLinkCommandIsIdempotentWhenLinkAlreadyExists(): void
    {
        $publicPath = $this->rootPath . '/public';
        $storageRoot = $this->rootPath . '/storage/app/public';
        $link = $publicPath . '/storage';

        mkdir($publicPath, 0777, true);
        mkdir($storageRoot, 0777, true);
        symlink($storageRoot, $link);

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => $storageRoot,
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Public storage link already exists at', $output);
        self::assertTrue(is_link($link));
    }

    public function testStorageLinkCommandFailsWhenSymlinkPointsElsewhere(): void
    {
        $publicPath = $this->rootPath . '/public';
        $storageRoot = $this->rootPath . '/storage/app/public';
        $otherRoot = $this->rootPath . '/storage/other';
        $link = $publicPath . '/storage';

        mkdir($publicPath, 0777, true);
        mkdir($storageRoot, 0777, true);
        mkdir($otherRoot, 0777, true);
        symlink($otherRoot, $link);

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => $storageRoot,
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('already exists but points somewhere else', $output);
    }

    public function testStorageLinkCommandFailsWhenPathExistsAsRegularDirectory(): void
    {
        $publicPath = $this->rootPath . '/public';
        $storageRoot = $this->rootPath . '/storage/app/public';
        $link = $publicPath . '/storage';

        mkdir($publicPath, 0777, true);
        mkdir($storageRoot, 0777, true);
        mkdir($link, 0777, true);

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => $storageRoot,
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('already exists and is not a symlink', $output);
    }

    public function testStorageLinkCommandExposesCliMetadataAndFallsBackToDefaultPublicDiskRoot(): void
    {
        $publicPath = $this->rootPath . '/public';
        $fallbackRoot = storage_path('app/public');

        mkdir($publicPath, 0777, true);
        if (!is_dir($fallbackRoot)) {
            mkdir($fallbackRoot, 0777, true);
        }

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => '   ',
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        self::assertSame('storage:link', $command->name());
        self::assertSame('Create the public storage symlink for files on the public disk.', $command->description());

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');
        $link = $publicPath . '/storage';

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Public storage link created at', $output);
        self::assertTrue(is_link($link));
        self::assertSame(realpath($fallbackRoot), realpath($link) ?: null);
    }

    public function testStorageLinkCommandNormalizesRelativeAndAbsolutePaths(): void
    {
        $publicPath = $this->rootPath . '/public';
        $storageRoot = $this->rootPath . '/storage/app/public';
        mkdir($publicPath, 0777, true);
        mkdir($storageRoot, 0777, true);

        $command = new StorageLinkCommand(new ConfigRepository(), $publicPath);

        $normalizePath = new \ReflectionMethod(StorageLinkCommand::class, 'normalizePath');
        $normalizePath->setAccessible(true);
        $isAbsolutePath = new \ReflectionMethod(StorageLinkCommand::class, 'isAbsolutePath');
        $isAbsolutePath->setAccessible(true);

        self::assertSame(
            realpath($storageRoot),
            $normalizePath->invoke($command, '../storage/app/public', $publicPath),
        );
        self::assertSame(
            rtrim($this->rootPath . '/missing/path', DIRECTORY_SEPARATOR),
            $normalizePath->invoke($command, $this->rootPath . '/missing/path'),
        );
        self::assertTrue($isAbsolutePath->invoke($command, '/tmp/storage'));
        self::assertFalse($isAbsolutePath->invoke($command, 'relative/path'));
        self::assertFalse($isAbsolutePath->invoke($command, ''));
    }

    public function testStorageLinkCommandFailsWhenConfiguredPublicPathCannotHostStorageDirectory(): void
    {
        $publicPath = $this->rootPath . '/public-file';
        $storageRoot = $this->rootPath . '/storage/app/public';
        file_put_contents($publicPath, 'not-a-directory');
        mkdir($storageRoot, 0777, true);

        $command = new StorageLinkCommand(
            new ConfigRepository([
                'storage' => [
                    'disks' => [
                        'public' => [
                            'root' => $storageRoot,
                        ],
                    ],
                ],
            ]),
            $publicPath,
        );

        [$exitCode, $output] = $this->runCommand($command, 'storage:link');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unable to create public directory', $output);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(CommandInterface $command, string $name): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $runner = new CommandRunner(new Container(), output: $stream);
        $runner->register($command);
        $exitCode = $runner->run(['myxa', $name]);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }

    private function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
