<?php

declare(strict_types=1);

namespace Test\Unit\RateLimit;

use App\RateLimit\RedisRateLimiterStore;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
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
}
