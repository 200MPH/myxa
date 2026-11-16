<?php

declare(strict_types=1);

namespace Test\Unit\Providers;

use App\Cache\Store\NullCacheStore;
use App\Config\ConfigRepository;
use App\Providers\CacheServiceProvider;
use Myxa\Application;
use Myxa\Cache\CacheManager;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Redis\RedisManager;
use Myxa\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

#[CoversClass(CacheServiceProvider::class)]
#[CoversClass(NullCacheStore::class)]
final class CacheEnabledTest extends TestCase
{
    public function testDisabledCachePreservesFileAndRedisEntriesUntilReenabled(): void
    {
        $path = sys_get_temp_dir() . '/myxa-disabled-cache-' . uniqid('', true);
        $configuration = [
            'cache' => [
                'default' => 'local',
                'stores' => [
                    'local' => ['driver' => 'file', 'path' => $path],
                    'redis' => ['driver' => 'redis', 'connection' => 'default'],
                ],
            ],
        ];
        $redis = new RedisManager('default', new RedisConnection(new InMemoryRedisStore()));
        $enabled = new Application();
        $enabled->instance(ConfigRepository::class, new ConfigRepository($configuration));
        $enabled->instance(RedisManager::class, $redis);
        $enabled->register(CacheServiceProvider::class);
        $enabled->boot();
        $original = $enabled->make(CacheManager::class);

        try {
            foreach (['local', 'redis'] as $alias) {
                self::assertTrue($original->put('existing', 'original', store: $alias));
            }

            $configuration['cache']['enabled'] = false;
            $disabled = new Application();
            $disabled->instance(ConfigRepository::class, new ConfigRepository($configuration));
            // No Redis manager: disabled stores must never initialize their backend.
            $disabled->register(CacheServiceProvider::class);
            $disabled->boot();
            $cache = Cache::getManager();

            foreach (['local', 'redis'] as $alias) {
                self::assertSame('fallback', $cache->get('existing', 'fallback', $alias));
                self::assertFalse($cache->has('existing', $alias));
                self::assertFalse($cache->put('existing', 'replacement', store: $alias));
                self::assertFalse($cache->forever('new', 'value', $alias));
                self::assertFalse($cache->forget('existing', $alias));
                self::assertTrue($cache->clear($alias));

                $calls = 0;
                $resolver = static function () use (&$calls): int {
                    return ++$calls;
                };
                self::assertSame(1, $cache->remember('existing', $resolver, store: $alias));
                self::assertSame(2, $cache->remember('existing', $resolver, store: $alias));

                $store = Cache::store($alias);
                self::assertInstanceOf(NullCacheStore::class, $store);
                self::assertSame('fallback', $store->get('existing', 'fallback'));
                self::assertFalse($store->put('existing', 'replacement'));
                self::assertFalse($store->forget('existing'));
                self::assertTrue($store->clear());
            }

            $configuration['cache']['enabled'] = true;
            $restored = new Application();
            $restored->instance(ConfigRepository::class, new ConfigRepository($configuration));
            $restored->instance(RedisManager::class, $redis);
            $restored->register(CacheServiceProvider::class);
            $restored->boot();
            $cache = Cache::getManager();

            foreach (['local', 'redis'] as $alias) {
                self::assertSame('original', $cache->get('existing', store: $alias));
                self::assertFalse($cache->has('new', $alias));
                self::assertTrue($cache->put('new', 'restored', store: $alias));
                self::assertSame('restored', $cache->get('new', store: $alias));
            }
        } finally {
            $original->clear('local');
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testDisabledCacheWithNoConfiguredStoresDoesNotFallBackToFileStorage(): void
    {
        $app = new Application();
        $app->instance(ConfigRepository::class, new ConfigRepository(['cache' => ['enabled' => false]]));
        $app->register(CacheServiceProvider::class);
        $app->boot();
        $cache = Cache::getManager();

        self::assertInstanceOf(NullCacheStore::class, Cache::store());
        self::assertFalse($cache->put('key', 'value'));
        self::assertNull($cache->get('key'));
    }

    /** @return iterable<string, array{string|null, bool}> */
    public static function enabledValues(): iterable
    {
        yield 'default' => [null, true];
        yield 'enabled' => ['true', true];
        yield 'disabled' => ['false', false];
        yield 'one' => ['1', true];
        yield 'zero' => ['0', false];
    }

    #[DataProvider('enabledValues')]
    public function testCacheConfigReadsEnvironmentFlag(?string $value, bool $expected): void
    {
        if ($value === null) {
            $this->unsetEnvironmentValue('CACHE_ENABLED');
        } else {
            $this->setEnvironmentValue('CACHE_ENABLED', $value);
        }

        $configuration = require config_path('cache.php');

        self::assertSame($expected, $configuration['enabled']);
    }
}
