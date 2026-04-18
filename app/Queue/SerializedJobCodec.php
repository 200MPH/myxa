<?php

declare(strict_types=1);

namespace App\Queue;

use Myxa\Queue\JobEnvelope;
use Myxa\Queue\JobInterface;
use RuntimeException;
use Throwable;

final class SerializedJobCodec
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function encode(
        JobInterface $job,
        array $context = [],
        ?string $queue = null,
        string $defaultQueue = 'default',
    ): array {
        $resolvedQueue = $this->resolveQueue($job, $queue, $defaultQueue);

        return [
            'id' => $this->generateId(),
            'queue' => $resolvedQueue,
            'job_class' => $job::class,
            'job_payload' => $this->encodeValue($job),
            'context_payload' => $this->encodeValue($context),
            'attempts' => 0,
            'available_at' => time() + $this->initialDelay($job),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function envelope(array $payload): JobEnvelope
    {
        $job = $this->decodeJob($payload['job_payload'] ?? null);
        $context = $this->decodeContext($payload['context_payload'] ?? null);

        return new JobEnvelope(
            id: (string) ($payload['id'] ?? ''),
            job: $job,
            queue: $this->stringValue($payload['queue'] ?? null),
            attempts: $this->intValue($payload['attempts'] ?? 0),
            context: $context,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function release(JobEnvelope $message, int $delaySeconds = 0): array
    {
        return [
            'id' => $message->id,
            'queue' => $message->queue ?? 'default',
            'job_class' => $message->job::class,
            'job_payload' => $this->encodeValue($message->job),
            'context_payload' => $this->encodeValue($message->context),
            'attempts' => $message->attempts + 1,
            'available_at' => time() + max(0, $delaySeconds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fail(JobEnvelope $message, ?Throwable $error = null): array
    {
        return [
            'id' => $message->id,
            'queue' => $message->queue ?? 'default',
            'job_class' => $message->job::class,
            'job_payload' => $this->encodeValue($message->job),
            'context_payload' => $this->encodeValue($message->context),
            'attempts' => $message->attempts + 1,
            'failed_at' => gmdate('c'),
            'error_message' => $error?->getMessage(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function retryFailed(array $payload, ?string $targetQueue = null): array
    {
        $queue = $this->stringValue($targetQueue)
            ?? $this->stringValue($payload['queue'] ?? null)
            ?? 'default';

        return [
            'id' => (string) ($payload['id'] ?? $this->generateId()),
            'queue' => $queue,
            'job_class' => (string) ($payload['job_class'] ?? 'unknown'),
            'job_payload' => $payload['job_payload'] ?? '',
            'context_payload' => $payload['context_payload'] ?? '',
            'attempts' => 0,
            'available_at' => time(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function failedRecord(array $payload): FailedJobRecord
    {
        return new FailedJobRecord(
            id: (string) ($payload['id'] ?? ''),
            queue: (string) ($payload['queue'] ?? 'default'),
            jobClass: (string) ($payload['job_class'] ?? 'unknown'),
            attempts: $this->intValue($payload['attempts'] ?? 0),
            failedAt: (string) ($payload['failed_at'] ?? gmdate('c')),
            errorMessage: $this->stringValue($payload['error_message'] ?? null),
        );
    }

    private function resolveQueue(JobInterface $job, ?string $queue, string $defaultQueue): string
    {
        $resolvedQueue = $queue;

        if ($resolvedQueue === null) {
            $resolvedQueue = JobMetadata::queue($job);
        }

        $resolvedQueue = is_string($resolvedQueue) ? trim($resolvedQueue) : '';

        return $resolvedQueue !== '' ? $resolvedQueue : $defaultQueue;
    }

    private function initialDelay(JobInterface $job): int
    {
        return JobMetadata::delaySeconds($job);
    }

    private function generateId(): string
    {
        return sprintf('job-%s', bin2hex(random_bytes(10)));
    }

    private function decodeJob(mixed $payload): JobInterface
    {
        $decoded = $this->decodeValue($payload);

        if (!$decoded instanceof JobInterface) {
            throw new RuntimeException('Queued payload did not decode into a valid job.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContext(mixed $payload): array
    {
        $decoded = $this->decodeValue($payload);

        return is_array($decoded) ? $decoded : [];
    }

    private function encodeValue(mixed $value): string
    {
        try {
            return base64_encode(serialize($value));
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to serialize queued payload.', previous: $exception);
        }
    }

    private function decodeValue(mixed $payload): mixed
    {
        if (!is_string($payload) || $payload === '') {
            throw new RuntimeException('Queued payload is missing or invalid.');
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Queued payload could not be base64 decoded.');
        }

        try {
            return unserialize($decoded, ['allowed_classes' => true]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Queued payload could not be unserialized.', previous: $exception);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
