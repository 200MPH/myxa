<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\CacheClearCommand;
use App\Console\Commands\CacheForgetCommand;
use Myxa\Cache\CacheManager;
use Myxa\Cache\Store\FileCacheStore;
use Myxa\Console\CommandInterface;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(CacheClearCommand::class)]
#[CoversClass(CacheForgetCommand::class)]
final class CacheCommandsTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-cache-command-' . uniqid('', true);
        mkdir($this->rootPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testCacheClearCommandRemovesEntriesFromDefaultStore(): void
    {
        $cachePath = $this->rootPath . '/cache/default';
        $manager = new CacheManager('local', new FileCacheStore($cachePath));
        $manager->put('dashboard:stats', ['users' => 12]);

        $command = new CacheClearCommand($manager);

        [$exitCode, $output] = $this->runCommand($command, 'cache:clear');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cache store [local] cleared.', $output);
        self::assertFalse($manager->has('dashboard:stats'));
        self::assertSame([], glob($cachePath . '/*.cache') ?: []);
    }

    public function testCacheClearCommandSupportsExplicitStoreOption(): void
    {
        $localPath = $this->rootPath . '/cache/local';
        $sessionPath = $this->rootPath . '/cache/session';
        $manager = new CacheManager('local', new FileCacheStore($localPath));
        $manager->addStore('session', new FileCacheStore($sessionPath));

        $manager->put('dashboard:stats', ['users' => 12], store: 'local');
        $manager->put('auth:session', ['active' => true], store: 'session');

        $command = new CacheClearCommand($manager);

        [$exitCode, $output] = $this->runCommand($command, 'cache:clear', options: ['store' => 'session']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cache store [session] cleared.', $output);
        self::assertTrue($manager->has('dashboard:stats', 'local'));
        self::assertFalse($manager->has('auth:session', 'session'));
    }

    public function testCacheForgetCommandRemovesSingleKeyFromDefaultStore(): void
    {
        $cachePath = $this->rootPath . '/cache/default';
        $manager = new CacheManager('local', new FileCacheStore($cachePath));
        $manager->put('dashboard:stats', ['users' => 12]);
        $manager->put('dashboard:meta', ['fresh' => true]);

        $command = new CacheForgetCommand($manager);

        [$exitCode, $output] = $this->runCommand(
            $command,
            'cache:forget',
            parameters: ['key' => 'dashboard:stats'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cache key [dashboard:stats] forgotten from store [local].', $output);
        self::assertFalse($manager->has('dashboard:stats'));
        self::assertTrue($manager->has('dashboard:meta'));
    }

    public function testCacheForgetCommandSupportsExplicitStoreOption(): void
    {
        $localPath = $this->rootPath . '/cache/local';
        $sessionPath = $this->rootPath . '/cache/session';
        $manager = new CacheManager('local', new FileCacheStore($localPath));
        $manager->addStore('session', new FileCacheStore($sessionPath));

        $manager->put('dashboard:stats', ['users' => 12], store: 'local');
        $manager->put('auth:session', ['active' => true], store: 'session');

        $command = new CacheForgetCommand($manager);

        [$exitCode, $output] = $this->runCommand(
            $command,
            'cache:forget',
            ['key' => 'auth:session'],
            ['store' => 'session'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cache key [auth:session] forgotten from store [session].', $output);
        self::assertTrue($manager->has('dashboard:stats', 'local'));
        self::assertFalse($manager->has('auth:session', 'session'));
    }

    public function testCacheForgetCommandTreatsMissingKeyAsNoOp(): void
    {
        $cachePath = $this->rootPath . '/cache/default';
        $manager = new CacheManager('local', new FileCacheStore($cachePath));

        $command = new CacheForgetCommand($manager);

        [$exitCode, $output] = $this->runCommand(
            $command,
            'cache:forget',
            parameters: ['key' => 'missing:key'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cache key [missing:key] was already absent from store [local].', $output);
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, string> $options
     * @return array{0: int, 1: string}
     */
    private function runCommand(
        CommandInterface $command,
        string $name,
        array $parameters = [],
        array $options = [],
    ): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput($name, $parameters, $options),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
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
