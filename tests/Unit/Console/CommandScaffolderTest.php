<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\CommandScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(CommandScaffolder::class)]
final class CommandScaffolderTest extends TestCase
{
    private string $commandsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-command-scaffolder-' . uniqid('', true);
        $this->commandsPath = $rootPath . '/app/Console/Commands';

        mkdir($this->commandsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->commandsPath))));

        parent::tearDown();
    }

    public function testMakeCreatesCommandFile(): void
    {
        $scaffolder = new CommandScaffolder($this->commandsPath);

        $result = $scaffolder->make('SendDigest', 'reports:send', 'Send the daily digest report.');
        $source = file_get_contents($result['path']);

        self::assertSame($this->commandsPath . '/SendDigestCommand.php', $result['path']);
        self::assertSame('App\\Console\\Commands\\SendDigestCommand', $result['class']);
        self::assertSame('reports:send', $result['command']);
        self::assertIsString($source);
        self::assertStringContainsString('final class SendDigestCommand extends Command', $source);
        self::assertStringContainsString("return 'reports:send';", $source);
        self::assertStringContainsString("return 'Send the daily digest report.';", $source);
    }

    public function testMakeInfersCommandNameFromClassName(): void
    {
        $scaffolder = new CommandScaffolder($this->commandsPath);

        $result = $scaffolder->make('PruneCacheCommand');
        $source = file_get_contents($result['path']);

        self::assertSame('prune:cache', $result['command']);
        self::assertIsString($source);
        self::assertStringContainsString("return 'prune:cache';", $source);
    }

    public function testMakeAcceptsSlashDelimitedCommandNames(): void
    {
        $scaffolder = new CommandScaffolder($this->commandsPath);

        $result = $scaffolder->make('Admin/SyncUsers');
        $source = file_get_contents($result['path']);

        self::assertSame($this->commandsPath . '/Admin/SyncUsersCommand.php', $result['path']);
        self::assertSame('App\\Console\\Commands\\Admin\\SyncUsersCommand', $result['class']);
        self::assertSame('sync:users', $result['command']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Console\\Commands\\Admin;', $source);
        self::assertStringContainsString('final class SyncUsersCommand extends Command', $source);
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
