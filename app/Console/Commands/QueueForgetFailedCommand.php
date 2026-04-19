<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Exceptions\CommandFailedException;
use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class QueueForgetFailedCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:forget-failed';
    }

    public function description(): string
    {
        return 'Delete one failed job from the queue dead-letter store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('id', 'Failed job identifier to delete.'),
        ];
    }

    protected function handle(): int
    {
        $id = (string) $this->parameter('id');

        if (!$this->queue->forgetFailed($id)) {
            throw new CommandFailedException(sprintf('Unable to delete failed job [%s].', $id));
        }

        $this->success(sprintf('Failed job [%s] deleted from the dead-letter store.', $id))->icon();

        return 0;
    }
}
