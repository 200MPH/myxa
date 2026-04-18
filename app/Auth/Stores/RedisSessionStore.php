<?php

declare(strict_types=1);

namespace App\Auth\Stores;

use App\Auth\SessionRecord;
use App\Auth\SessionRecordInterface;
use App\Auth\SessionStoreInterface;
use DateTimeImmutable;
use JsonException;
use Myxa\Redis\RedisManager;

final class RedisSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly RedisManager $redis,
        private readonly string $connection,
        private readonly string $prefix = 'session:',
    ) {
    }

    public function issue(
        int $userId,
        string $plainTextSession,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): SessionRecordInterface {
        $record = [
            'identifier' => $this->identifier($plainTextSession),
            'user_id' => $userId,
            'driver' => 'redis',
            'last_used_at' => $now->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'revoked_at' => null,
        ];

        $this->redis->set(
            $this->key($plainTextSession),
            json_encode($record, JSON_THROW_ON_ERROR),
            $this->connection,
        );

        return $this->hydrate($record);
    }

    public function resolve(string $plainTextSession, DateTimeImmutable $now): ?SessionRecordInterface
    {
        $payload = $this->redis->get($this->key($plainTextSession), $this->connection);
        if (!is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $record = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($record)) {
            return null;
        }

        $session = $this->hydrate($record);
        if ($session->revoked() || $session->expired($now)) {
            return null;
        }

        $record['last_used_at'] = $now->format(DATE_ATOM);
        $this->redis->set(
            $this->key($plainTextSession),
            json_encode($record, JSON_THROW_ON_ERROR),
            $this->connection,
        );

        return $this->hydrate($record);
    }

    public function revoke(string $identifier, DateTimeImmutable $now): bool
    {
        $payload = $this->redis->get($this->keyByIdentifier($identifier), $this->connection);
        if (!is_string($payload) || $payload === '') {
            return false;
        }

        try {
            $record = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (!is_array($record)) {
            return false;
        }

        if (($record['revoked_at'] ?? null) !== null) {
            return true;
        }

        $record['revoked_at'] = $now->format(DATE_ATOM);

        return $this->redis->set(
            $this->keyByIdentifier($identifier),
            json_encode($record, JSON_THROW_ON_ERROR),
            $this->connection,
        );
    }

    private function hydrate(array $record): SessionRecord
    {
        return new SessionRecord(
            (string) ($record['identifier'] ?? ''),
            (int) ($record['user_id'] ?? 0),
            'redis',
            $this->date($record['last_used_at'] ?? null),
            $this->date($record['expires_at'] ?? null),
            $this->date($record['revoked_at'] ?? null),
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function identifier(string $plainTextSession): string
    {
        return hash('sha256', $plainTextSession);
    }

    private function key(string $plainTextSession): string
    {
        return $this->keyByIdentifier($this->identifier($plainTextSession));
    }

    private function keyByIdentifier(string $identifier): string
    {
        return $this->prefix . $identifier;
    }
}
