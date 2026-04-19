<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Exceptions\CommandFailedException;
use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class QueueRetryCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:retry';
    }

    public function description(): string
    {
        return 'Retry one failed job from the queue dead-letter store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('id', 'Failed job identifier to retry.'),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('queue', 'Optional target queue name for the retried job.', true),
        ];
    }

    protected function handle(): int
    {
        $id = (string) $this->parameter('id');
        $targetQueue = $this->stringOption('queue');

        if (!$this->queue->retryFailed($id, $targetQueue)) {
            throw new CommandFailedException(sprintf('Unable to retry failed job [%s].', $id));
        }

        $this->success(sprintf('Failed job [%s] moved back to the queue.', $id))->icon();

        return 0;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
