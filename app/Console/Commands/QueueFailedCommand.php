<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\InspectableQueueInterface;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class QueueFailedCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:failed';
    }

    public function description(): string
    {
        return 'List failed queued jobs recorded by the configured queue store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('queue', 'Optional queue name to filter failed jobs by.', false),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('limit', 'Maximum failed jobs to display.', true),
        ];
    }

    protected function handle(): int
    {
        $records = $this->queue->failed($this->stringParameter('queue'), $this->intOption('limit') ?? 50);

        if ($records === []) {
            $this->info('No failed jobs recorded.')->icon();

            return 0;
        }

        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                $record->id,
                $record->queue,
                $record->jobClass,
                (string) $record->attempts,
                $record->failedAt,
                $record->errorMessage ?? '-',
            ];
        }

        $this->table(['ID', 'Queue', 'Job', 'Attempts', 'Failed At', 'Error'], $rows);

        return 0;
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

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
