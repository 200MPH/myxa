<?php

declare(strict_types=1);

namespace App\Auth\Stores;

use App\Auth\SessionRecord;
use App\Auth\SessionRecordInterface;
use App\Auth\SessionStoreInterface;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class FileSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function issue(
        int $userId,
        string $plainTextSession,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): SessionRecordInterface {
        $this->ensureDirectory();

        $record = [
            'identifier' => $this->identifier($plainTextSession),
            'user_id' => $userId,
            'driver' => 'file',
            'last_used_at' => $now->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'revoked_at' => null,
        ];

        $this->write($plainTextSession, $record);

        return $this->hydrate($record);
    }

    public function resolve(string $plainTextSession, DateTimeImmutable $now): ?SessionRecordInterface
    {
        $record = $this->read($plainTextSession);
        if ($record === null) {
            return null;
        }

        $session = $this->hydrate($record);
        if ($session->revoked() || $session->expired($now)) {
            return null;
        }

        $record['last_used_at'] = $now->format(DATE_ATOM);
        $this->write($plainTextSession, $record);

        return $this->hydrate($record);
    }

    public function revoke(string $identifier, DateTimeImmutable $now): bool
    {
        $file = $this->fileByIdentifier($identifier);
        if (!is_file($file)) {
            return false;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents) || $contents === '') {
            return false;
        }

        try {
            $record = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
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
        file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return true;
    }

    private function read(string $plainTextSession): ?array
    {
        $file = $this->file($plainTextSession);
        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function write(string $plainTextSession, array $record): void
    {
        $this->ensureDirectory();

        file_put_contents(
            $this->file($plainTextSession),
            json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function hydrate(array $record): SessionRecord
    {
        return new SessionRecord(
            (string) ($record['identifier'] ?? ''),
            (int) ($record['user_id'] ?? 0),
            'file',
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

    private function file(string $plainTextSession): string
    {
        return $this->fileByIdentifier($this->identifier($plainTextSession));
    }

    private function fileByIdentifier(string $identifier): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $identifier . '.json';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->path, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create session directory [%s].', $this->path));
        }
    }
}
