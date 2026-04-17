<?php

declare(strict_types=1);

namespace Test\Unit\RateLimit;

use App\RateLimit\RedisRateLimiterStore;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Redis\Connection\RedisStoreInterface;
use Myxa\Redis\RedisManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(RedisRateLimiterStore::class)]
final class RedisRateLimiterStoreTest extends TestCase
{
    public function testIncrementTracksAttemptsAndExpiryInRedisPayload(): void
    {
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $store = new RedisRateLimiterStore($redis, 'rate-limit', 'limits:');

        $first = $store->increment('login|127.0.0.1', 60, 100);
        $second = $store->increment('login|127.0.0.1', 60, 101);

        self::assertSame(1, $first->attempts);
        self::assertSame(160, $first->expiresAt);
        self::assertSame(2, $second->attempts);
        self::assertSame(160, $second->expiresAt);

        $payload = $redis->get('limits:' . sha1('login|127.0.0.1'), 'rate-limit');
        self::assertIsString($payload);
        self::assertStringContainsString('"attempts":2', $payload);
    }

    public function testIncrementResetsAfterWindowExpires(): void
    {
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $store = new RedisRateLimiterStore($redis, 'rate-limit');

        $store->increment('api|127.0.0.1', 10, 100);
        $result = $store->increment('api|127.0.0.1', 10, 111);

        self::assertSame(1, $result->attempts);
        self::assertSame(121, $result->expiresAt);
    }

    public function testClearRemovesBucket(): void
    {
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $store = new RedisRateLimiterStore($redis, 'rate-limit');
        $key = 'uploads|127.0.0.1';

        $store->increment($key, 60, 100);
        self::assertTrue($redis->has('rate-limit:' . sha1($key), 'rate-limit'));

        $store->clear($key);

        self::assertFalse($redis->has('rate-limit:' . sha1($key), 'rate-limit'));
    }

    public function testIncrementRecoversFromInvalidStoredPayload(): void
    {
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $redis->set('rate-limit:' . sha1('broken|127.0.0.1'), '{invalid-json', 'rate-limit');

        $store = new RedisRateLimiterStore($redis, 'rate-limit');
        $result = $store->increment('broken|127.0.0.1', 30, 100);

        self::assertSame(1, $result->attempts);
        self::assertSame(130, $result->expiresAt);
    }

    public function testIncrementResetsWhenStoredPayloadContainsInvalidNumericState(): void
    {
        $redis = new RedisManager('rate-limit', new RedisConnection(new InMemoryRedisStore()));
        $redis->set(
            'rate-limit:' . sha1('stringy|127.0.0.1'),
            '{"attempts":"two","expires_at":"tomorrow"}',
            'rate-limit',
        );

        $store = new RedisRateLimiterStore($redis, 'rate-limit');
        $result = $store->increment('stringy|127.0.0.1', 45, 100);

        self::assertSame(1, $result->attempts);
        self::assertSame(145, $result->expiresAt);
    }

    public function testIncrementThrowsWhenRedisPersistenceFails(): void
    {
        $failingStore = new class implements RedisStoreInterface
        {
            public function get(string $key): string|int|float|bool|null
            {
                return null;
            }

            public function set(string $key, string|int|float|bool|null $value): bool
            {
                return false;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }

            public function increment(string $key, int $by = 1): int
            {
                return $by;
            }
        };

        $redis = new RedisManager('rate-limit', new RedisConnection($failingStore));
        $store = new RedisRateLimiterStore($redis, 'rate-limit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to persist Redis rate limit key');

        $store->increment('failing|127.0.0.1', 30, 100);
    }
}
