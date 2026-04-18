<?php

declare(strict_types=1);

namespace App\RateLimit;

use JsonException;
use Myxa\RateLimit\RateLimitCounter;
use Myxa\RateLimit\RateLimiterStoreInterface;
use Myxa\Redis\Connection\PhpRedisStore;
use Myxa\Redis\RedisManager;
use RuntimeException;

final class RedisRateLimiterStore implements RateLimiterStoreInterface
{
    private const string LUA_INCREMENT = <<<'LUA'
local raw = redis.call('GET', KEYS[1])
local now = tonumber(ARGV[1])
local decay = tonumber(ARGV[2])

if not raw then
  local expires_at = now + decay
  redis.call('SET', KEYS[1], cjson.encode({attempts = 1, expires_at = expires_at}))
  redis.call('EXPIREAT', KEYS[1], expires_at)
  return {1, expires_at}
end

local data = cjson.decode(raw)
local attempts = tonumber(data["attempts"]) or 0
local expires_at = tonumber(data["expires_at"]) or 0

if expires_at <= now then
  attempts = 0
  expires_at = now + decay
end

attempts = attempts + 1

redis.call('SET', KEYS[1], cjson.encode({attempts = attempts, expires_at = expires_at}))
redis.call('EXPIREAT', KEYS[1], expires_at)

return {attempts, expires_at}
LUA;

    public function __construct(
        private readonly RedisManager $redis,
        private readonly ?string $connection = null,
        private readonly string $prefix = 'rate-limit:',
    ) {
    }

    public function increment(string $key, int $decaySeconds, int $now): RateLimitCounter
    {
        $storageKey = $this->storageKey($key);
        $store = $this->redis->connection($this->connection)->store();

        if ($store instanceof PhpRedisStore) {
            return $this->incrementAtomically($store, $storageKey, $decaySeconds, $now);
        }

        return $this->incrementViaManager($storageKey, $decaySeconds, $now);
    }

    public function clear(string $key): void
    {
        $this->redis->delete($this->storageKey($key), $this->connection);
    }

    private function storageKey(string $key): string
    {
        return $this->prefix . sha1($key);
    }

    private function incrementAtomically(
        PhpRedisStore $store,
        string $storageKey,
        int $decaySeconds,
        int $now,
    ): RateLimitCounter {
        $result = $store->client()->eval(self::LUA_INCREMENT, [$storageKey, (string) $now, (string) $decaySeconds], 1);

        if (!is_array($result) || !isset($result[0], $result[1])) {
            throw new RuntimeException('Redis rate limit script returned an invalid payload.');
        }

        return new RateLimitCounter((int) $result[0], (int) $result[1]);
    }

    private function incrementViaManager(string $storageKey, int $decaySeconds, int $now): RateLimitCounter
    {
        $state = $this->readState($storageKey);
        $attempts = $state['attempts'] ?? 0;
        $expiresAt = $state['expires_at'] ?? 0;

        if ($expiresAt <= $now) {
            $attempts = 0;
            $expiresAt = $now + $decaySeconds;
        }

        $attempts++;

        $this->writeState($storageKey, [
            'attempts' => $attempts,
            'expires_at' => $expiresAt,
        ]);

        return new RateLimitCounter($attempts, $expiresAt);
    }

    /**
     * @return array{attempts?: int, expires_at?: int}
     */
    private function readState(string $storageKey): array
    {
        $payload = $this->redis->get($storageKey, $this->connection);

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return [];
        }

        return [
            'attempts' => isset($decoded['attempts']) && is_int($decoded['attempts'])
                ? $decoded['attempts']
                : null,
            'expires_at' => isset($decoded['expires_at']) && is_int($decoded['expires_at'])
                ? $decoded['expires_at']
                : null,
        ];
    }

    /**
     * @param array{attempts: int, expires_at: int} $state
     */
    private function writeState(string $storageKey, array $state): void
    {
        try {
            $payload = json_encode($state, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode Redis rate limit state.', previous: $exception);
        }

        if (!is_string($payload) || !$this->redis->set($storageKey, $payload, $this->connection)) {
            throw new RuntimeException(sprintf('Unable to persist Redis rate limit key [%s].', $storageKey));
        }
    }
}
