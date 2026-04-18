<?php

declare(strict_types=1);

namespace App\Queue;

use Myxa\Queue\QueueInterface;

interface InspectableQueueInterface extends QueueInterface
{
    /**
     * @return list<QueueStats>
     */
    public function stats(?string $queue = null): array;

    /**
     * @return list<FailedJobRecord>
     */
    public function failed(?string $queue = null, int $limit = 50): array;

    public function retryFailed(string $id, ?string $targetQueue = null): bool;

    public function retryAllFailed(?string $queue = null, int $limit = 0, ?string $targetQueue = null): int;

    public function forgetFailed(string $id): bool;

    public function pruneFailed(int $olderThanSeconds, ?string $queue = null): int;

    public function flushFailed(?string $queue = null): int;
}
