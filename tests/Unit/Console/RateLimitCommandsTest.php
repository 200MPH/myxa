<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Config\ConfigRepository;
use App\Console\Commands\RateLimitClearCommand;
use App\RateLimit\RedisRateLimiterStore;
use Myxa\Application;
use Myxa\Console\CommandInterface;
use Myxa\Console\CommandRunner;
use Myxa\Container\Container;
use Myxa\RateLimit\FileRateLimiterStore;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Redis\RedisManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RateLimitClearCommand::class)]
final class RateLimitCommandsTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-rate-limit-command-' . uniqid('', true);
        mkdir($this->rootPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testRateLimitClearCommandWarnsBeforeClearingWithoutForce(): void
    {
        $storePath = $this->rootPath . '/rate-limit/default';
        $store = new FileRateLimiterStore($storePath);
        $store->increment('api|127.0.0.1', 60, time());

        $command = new RateLimitClearCommand(
            new Application(),
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'file',
                    'stores' => [
                        'file' => [
                            'driver' => 'file',
                            'path' => $storePath,
                        ],
                    ],
                ],
            ]),
        );

        [$exitCode, $output] = $this->runCommand($command, 'rate-limit:clear');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Re-run with --force to continue.', $output);
        self::assertCount(1, glob($storePath . '/*.json') ?: []);
    }

    public function testRateLimitClearCommandClearsEntriesFromDefaultFileStore(): void
    {
        $storePath = $this->rootPath . '/rate-limit/default';
        $store = new FileRateLimiterStore($storePath);
        $store->increment('api|127.0.0.1', 60, time());
        $store->increment('login|127.0.0.1', 60, time());

        $command = new RateLimitClearCommand(
            new Application(),
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'file',
                    'stores' => [
                        'file' => [
                            'driver' => 'file',
                            'path' => $storePath,
                        ],
                    ],
                ],
            ]),
        );

        [$exitCode, $output] = $this->runCommand($command, 'rate-limit:clear', options: ['force' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cleared 2 rate-limit entries', $output);
        self::assertSame([], glob($storePath . '/*.json') ?: []);
    }

    public function testRateLimitClearCommandSupportsExplicitStoreOption(): void
    {
        $defaultPath = $this->rootPath . '/rate-limit/default';
        $secondaryPath = $this->rootPath . '/rate-limit/secondary';

        (new FileRateLimiterStore($defaultPath))->increment('api|127.0.0.1', 60, time());
        (new FileRateLimiterStore($secondaryPath))->increment('login|127.0.0.1', 60, time());

        $command = new RateLimitClearCommand(
            new Application(),
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'file',
                    'stores' => [
                        'file' => [
                            'driver' => 'file',
                            'path' => $defaultPath,
                        ],
                        'secondary' => [
                            'driver' => 'file',
                            'path' => $secondaryPath,
                        ],
                    ],
                ],
            ]),
        );

        [$exitCode, $output] = $this->runCommand(
            $command,
            'rate-limit:clear',
            options: ['store' => 'secondary', 'force' => true],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('store [secondary]', $output);
        self::assertCount(1, glob($defaultPath . '/*.json') ?: []);
        self::assertSame([], glob($secondaryPath . '/*.json') ?: []);
    }

    public function testRateLimitClearCommandRejectsPrefixForFileStores(): void
    {
        $storePath = $this->rootPath . '/rate-limit/default';

        $command = new RateLimitClearCommand(
            new Application(),
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'file',
                    'stores' => [
                        'file' => [
                            'driver' => 'file',
                            'path' => $storePath,
                        ],
                    ],
                ],
            ]),
        );

        [$exitCode, $output] = $this->runCommand(
            $command,
            'rate-limit:clear',
            options: ['prefix' => 'rate-limit:api:', 'force' => true],
        );

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('only supported for Redis-backed rate-limit stores', $output);
    }

    public function testRateLimitClearCommandClearsRedisEntriesByPrefix(): void
    {
        $app = new Application();
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $app->instance(RedisManager::class, $redis);

        (new RedisRateLimiterStore($redis, 'rate-limit', 'limits:api:'))->increment('users|127.0.0.1', 60, time());
        (new RedisRateLimiterStore($redis, 'rate-limit', 'limits:login:'))->increment('users|127.0.0.1', 60, time());

        $command = new RateLimitClearCommand(
            $app,
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'redis',
                    'stores' => [
                        'redis' => [
                            'driver' => 'redis',
                            'connection' => 'rate-limit',
                            'prefix' => 'limits:',
                        ],
                    ],
                ],
            ]),
        );

        [$exitCode, $output] = $this->runCommand(
            $command,
            'rate-limit:clear',
            options: ['prefix' => 'limits:api:', 'force' => true],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Redis prefix [limits:api:]', $output);
        self::assertFalse($redis->has('limits:api:' . sha1('users|127.0.0.1'), 'rate-limit'));
        self::assertTrue($redis->has('limits:login:' . sha1('users|127.0.0.1'), 'rate-limit'));
    }

    public function testRateLimitClearCommandExposesExpectedCliMetadata(): void
    {
        $command = new RateLimitClearCommand(
            new Application(),
            new ConfigRepository([
                'rate_limit' => [
                    'default_store' => 'file',
                    'stores' => [
                        'file' => [
                            'driver' => 'file',
                            'path' => $this->rootPath . '/rate-limit/default',
                        ],
                    ],
                ],
            ]),
        );

        self::assertSame('rate-limit:clear', $command->name());
        self::assertSame(
            'Clear persisted rate-limit counters from the configured rate-limit store.',
            $command->description(),
        );
        self::assertCount(3, $command->options());
        self::assertSame('store', $command->options()[0]->name());
        self::assertSame('prefix', $command->options()[1]->name());
        self::assertSame('force', $command->options()[2]->name());
        self::assertFalse($command->options()[2]->acceptsValue());
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, mixed> $options
     * @return array{0: int, 1: string}
     */
    private function runCommand(
        CommandInterface $command,
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
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function argv(string $name, array $parameters, array $options): array
    {
        $argv = ['myxa', $name];

        foreach ($parameters as $value) {
            $argv[] = $value;
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
