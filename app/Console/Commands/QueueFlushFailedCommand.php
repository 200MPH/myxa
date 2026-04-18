<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class QueueFlushFailedCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:flush-failed';
    }

    public function description(): string
    {
        return 'Delete failed queued jobs recorded by the configured queue store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('queue', 'Optional queue name to limit the failed-job cleanup to.', false),
        ];
    }

    protected function handle(): int
    {
        $queue = $this->stringParameter('queue');
        $deleted = $this->queue->flushFailed($queue);
        $label = $queue ?? 'all queues';

        $this->success(sprintf('Removed %d failed job(s) from [%s].', $deleted, $label))->icon();

        return 0;
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
