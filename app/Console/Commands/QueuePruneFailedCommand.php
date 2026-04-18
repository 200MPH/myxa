<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\InspectableQueueInterface;
use InvalidArgumentException;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class QueuePruneFailedCommand extends Command
{
    public function __construct(private readonly InspectableQueueInterface $queue)
    {
    }

    public function name(): string
    {
        return 'queue:prune-failed';
    }

    public function description(): string
    {
        return 'Delete failed jobs older than the requested age from the dead-letter store.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument('queue', 'Optional failed queue name to prune.', false),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption(
                'older-than',
                'Minimum age of failed jobs to delete, for example 30m, 12h, or 7d.',
                true,
                true,
            ),
        ];
    }

    protected function handle(): int
    {
        $queue = $this->stringParameter('queue');
        $rawAge = $this->stringOption('older-than');

        if ($rawAge === null) {
            $this->error('The [older-than] option is required.')->icon();

            return 1;
        }

        try {
            $olderThanSeconds = $this->parseAge($rawAge);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage())->icon();

            return 1;
        }

        $pruned = $this->queue->pruneFailed($olderThanSeconds, $queue);
        $label = $queue ?? 'all queues';

        $this->success(sprintf('Pruned %d failed job(s) from [%s].', $pruned, $label))->icon();

        return 0;
    }

    private function parseAge(string $value): int
    {
        $value = strtolower(trim($value));

        if (!preg_match('/^(\d+)([smhdw])$/', $value, $matches)) {
            throw new InvalidArgumentException(
                'Invalid [older-than] value. Use a number followed by s, m, h, d, or w, for example 30m or 7d.',
            );
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            's' => $amount,
            'm' => $amount * 60,
            'h' => $amount * 3600,
            'd' => $amount * 86400,
            'w' => $amount * 604800,
        };
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
}
