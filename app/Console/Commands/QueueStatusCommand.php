<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;

final class QueueStatusCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:status';
    }

    public function description(): string
    {
        return 'Show ready, delayed, reserved, and failed counts for the configured queue store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('queue', 'Optional queue name to inspect. Omitting it shows all known queues.', false),
        ];
    }

    protected function handle(): int
    {
        $rows = [];

        foreach ($this->queue->stats($this->stringParameter('queue')) as $stats) {
            $rows[] = [
                $stats->queue,
                (string) $stats->ready,
                (string) $stats->delayed,
                (string) $stats->reserved,
                (string) $stats->failed,
            ];
        }

        $this->table(['Queue', 'Ready', 'Delayed', 'Reserved', 'Failed'], $rows);

        return 0;
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
