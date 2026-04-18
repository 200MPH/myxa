<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class QueueRetryAllCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:retry-all';
    }

    public function description(): string
    {
        return 'Retry failed jobs from the queue dead-letter store in bulk.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('queue', 'Optional failed queue name to filter by.', false),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('limit', 'Maximum failed jobs to retry.', true),
            new InputOption('target-queue', 'Optional target queue name for retried jobs.', true),
        ];
    }

    protected function handle(): int
    {
        $queue = $this->stringParameter('queue');
        $limit = $this->intOption('limit') ?? 0;
        $targetQueue = $this->stringOption('target-queue');

        $retried = $this->queue->retryAllFailed($queue, $limit, $targetQueue);
        $label = $queue ?? 'all queues';

        $this->success(sprintf('Retried %d failed job(s) from [%s].', $retried, $label))->icon();

        return 0;
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        if (!is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
