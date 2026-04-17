<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Config\ConfigRepository;
use App\Console\Commands\StorageLinkCommand;
use Myxa\Console\CommandInterface;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
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
