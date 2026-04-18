<?php

declare(strict_types=1);

namespace App\Queue;

use Myxa\Queue\JobInterface;
use Myxa\Queue\QueuedJobInterface;

final class JobMetadata
{
    public static function queue(JobInterface $job): ?string
    {
        if ($job instanceof QueuedJobInterface) {
            return self::normalizeQueue($job->queue());
        }

        if (is_callable([$job, 'queue'])) {
            /** @var mixed $value */
            $value = $job->queue();

            return self::normalizeQueue($value);
        }

        return null;
    }

    public static function delaySeconds(JobInterface $job): int
    {
        if ($job instanceof QueuedJobInterface) {
            return max(0, $job->delaySeconds());
        }

        if (is_callable([$job, 'delaySeconds'])) {
            /** @var mixed $value */
            $value = $job->delaySeconds();

            return is_numeric($value) ? max(0, (int) $value) : 0;
        }

        return 0;
    }

    public static function maxAttempts(JobInterface $job, int $default): int
    {
        if ($job instanceof QueuedJobInterface) {
            return max(1, $job->maxAttempts());
        }

        if (is_callable([$job, 'maxAttempts'])) {
            /** @var mixed $value */
            $value = $job->maxAttempts();

            return is_numeric($value) ? max(1, (int) $value) : max(1, $default);
        }

        return max(1, $default);
    }

    private static function normalizeQueue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
