<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queue\QueueWorker;
use Myxa\Console\Command;
use Myxa\Console\InputArgument;
use Myxa\Console\InputOption;

final class QueueWorkCommand extends Command
{
    public function __construct(private readonly QueueWorker $worker)
    {
    }

    public function name(): string
    {
        return 'queue:work';
    }

    public function description(): string
    {
        return 'Process queued jobs from the configured queue connection.';
    }

    public function parameters(): array
    {
        return [
            new InputArgument(
                'queue',
                'Optional queue name to process. Uses the configured default queue when omitted.',
                false,
            ),
        ];
    }

    public function options(): array
    {
        return [
            new InputOption('once', 'Process at most one immediately available job and then exit.'),
            new InputOption('sleep', 'Seconds to wait between empty queue polls.', true),
            new InputOption('max-jobs', 'Stop after processing this many jobs.', true),
            new InputOption('max-idle', 'Stop after this many empty polls. Zero keeps the worker alive.', true),
        ];
    }

    protected function handle(): int
    {
        $queue = $this->stringParameter('queue');
        $once = $this->boolOption('once');
        $sleep = $this->intOption('sleep');
        $maxJobs = $once ? 1 : $this->intOption('max-jobs');
        $maxIdle = $once ? 1 : $this->intOption('max-idle');

        $this->registerSignalHandlers();

        $label = $queue ?? 'default';
        $this->info(sprintf('Starting queue worker for [%s].', $label))->icon();

        $exitCode = $this->worker->work(
            queue: $queue,
            once: $once,
            sleepSeconds: $sleep,
            maxJobs: $maxJobs,
            maxIdleCycles: $maxIdle,
        );

        $this->success(sprintf('Queue worker for [%s] finished.', $label))->icon();

        return $exitCode;
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->warning('Stopping queue worker after the current job finishes.')->icon();
            $this->worker->stop();
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }

    private function stringParameter(string $name): ?string
    {
        $value = $this->parameter($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function boolOption(string $name): bool
    {
        $value = $this->option($name, false);

        return $value === true || $value === 'true' || $value === '1' || $value === 1;
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
