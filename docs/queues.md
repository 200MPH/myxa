# Queues

Queues let the app move slow or retryable work out of the HTTP request path.

The project wires the framework queue contracts into an app-level queue layer with:

- plain PHP job classes
- file and Redis queue backends
- worker commands
- failed-job inspection and retry commands

## On This Page

- [Quick Example](#quick-example)
- [Configuration](#configuration)
- [Backends](#backends)
- [Jobs](#jobs)
- [Dispatching Jobs](#dispatching-jobs)
- [Working Jobs](#working-jobs)
- [Failed Jobs](#failed-jobs)
- [Named Queues](#named-queues)
- [Adding Another Backend](#adding-another-backend)
- [Related Guides](#related-guides)

## Quick Example

Create a job:

```php
use Myxa\Queue\JobInterface;

final readonly class SendWelcomeEmailJob implements JobInterface
{
    public function __construct(private int $userId)
    {
    }

    public function handle(): void
    {
        // Load the user and send the email.
    }
}
```

Dispatch it from app code:

```php
use Myxa\Queue\QueueInterface;

final readonly class SignupService
{
    public function __construct(private QueueInterface $queue)
    {
    }

    public function welcome(int $userId): void
    {
        $this->queue->push(new SendWelcomeEmailJob($userId));
    }
}
```

Run a worker:

```bash
./myxa queue:work
```

For local debugging, process one available job and exit:

```bash
./myxa queue:work --once
```

## Configuration

Queue config lives in:

```text
config/queue.php
```

Useful environment variables:

```text
QUEUE_CONNECTION=file
QUEUE_NAME=default
QUEUE_VISIBILITY_TIMEOUT=60
QUEUE_REDIS_CONNECTION=default
QUEUE_REDIS_PREFIX=queue:
QUEUE_WORKER_SLEEP=3
QUEUE_WORKER_MAX_IDLE=0
QUEUE_WORKER_MAX_ATTEMPTS=3
QUEUE_WORKER_BACKOFF=30
```

## Backends

Supported queue stores:

- `file`: local filesystem queue under `storage/queue`
- `redis`: shared Redis queue for multi-node deployments

Recommended usage:

- use `file` for local development and small single-node apps
- use `redis` when multiple app or worker nodes need to consume the same queue

Both shipped drivers support reservation recovery. If a worker crashes after reserving a job but before acknowledging it, the job is returned to the ready queue after `QUEUE_VISIBILITY_TIMEOUT` expires.

## Jobs

Jobs should implement `JobInterface` and expose a `handle()` method:

```php
use Myxa\Queue\JobInterface;

final class GenerateReportJob implements JobInterface
{
    public function __construct(private int $reportId)
    {
    }

    public function handle(): void
    {
        // Generate the report.
    }
}
```

The project also includes `App\Queue\Quable` for job-level queue metadata. The name is intentional in this codebase.

```php
use App\Queue\Quable;
use Myxa\Queue\JobInterface;

final class GenerateInvoiceJob implements JobInterface
{
    use Quable;

    public function __construct(private int $invoiceId)
    {
    }

    public function handle(): void
    {
        // Build the invoice.
    }

    public function queue(): ?string
    {
        return 'documents';
    }

    public function delaySeconds(): int
    {
        return 0;
    }

    public function maxAttempts(): int
    {
        return 3;
    }
}
```

Recommended project style:

- always implement `JobInterface`
- add `Quable` when the job declares queue name, delay, or retry defaults
- keep queued payloads small and serializable

Good payloads are IDs, strings, numbers, booleans, arrays, and other simple serializable values. Avoid putting live services, database connections, open files, uploaded files, or request objects on queued jobs.

Legacy `QueuedJobInterface` jobs still work, but `JobInterface` plus `Quable` is the simpler app-facing pattern.

## Dispatching Jobs

Inject `QueueInterface` anywhere in the app:

```php
use Myxa\Queue\QueueInterface;

final readonly class InvoiceService
{
    public function __construct(private QueueInterface $queue)
    {
    }

    public function generate(int $invoiceId): void
    {
        $this->queue->push(new GenerateInvoiceJob($invoiceId), [
            'source' => 'invoice-service',
        ]);
    }
}
```

You can also force a queue name at dispatch time:

```php
$queue->push(new GenerateInvoiceJob($invoiceId), queue: 'documents');
```

Jobs are serialized before being persisted, so queued jobs should carry the data needed to reload state later rather than carrying live runtime objects.

## Working Jobs

Process jobs from the default queue:

```bash
./myxa queue:work
```

Process jobs from a named queue:

```bash
./myxa queue:work emails
```

Useful worker options:

- `--once`: process at most one available job, then exit
- `--sleep=<seconds>`: wait this long after an empty poll before checking again
- `--max-jobs=<count>`: stop after processing this many jobs
- `--max-idle=<count>`: stop after this many empty polls; `0` means keep running

Examples:

```bash
./myxa queue:work emails --once
./myxa queue:work emails --sleep=1 --max-idle=5
./myxa queue:work emails --max-jobs=500
```

Practical guidance:

- use `--once` for local debugging and CI-style checks
- use short `--max-idle` values for short-lived workers
- leave `--max-idle=0` for production workers that should keep polling
- use `--max-jobs` when you want long-running workers to recycle periodically

Inspect queues:

```bash
./myxa queue:status
./myxa queue:status emails
```

## Failed Jobs

Jobs that exhaust retries are moved to the failed-job store. This acts as the project DLQ.

Inspect failed jobs:

```bash
./myxa queue:failed
./myxa queue:failed emails --limit=20
```

Retry failed jobs:

```bash
./myxa queue:retry job-123
./myxa queue:retry-all
./myxa queue:retry-all emails --limit=20
```

Delete failed jobs:

```bash
./myxa queue:forget-failed job-123
./myxa queue:prune-failed --older-than=7d
./myxa queue:prune-failed emails --older-than=30d
./myxa queue:flush-failed
./myxa queue:flush-failed emails
```

Command differences:

- `queue:retry <id>` retries one failed job
- `queue:retry-all [queue]` retries many failed jobs
- `queue:forget-failed <id>` deletes one failed job
- `queue:flush-failed [queue]` deletes all failed jobs for that scope
- `queue:prune-failed [queue] --older-than=7d` deletes only old failed jobs

`queue:prune-failed` supports age suffixes:

- `s`: seconds
- `m`: minutes
- `h`: hours
- `d`: days
- `w`: weeks

## Named Queues

Queue names are logical channels chosen by the job or by the caller. You do not need special config just to use a non-default queue name.

Declare the queue on the job:

```php
use App\Queue\Quable;
use Myxa\Queue\JobInterface;

final readonly class SendWelcomeEmailJob implements JobInterface
{
    use Quable;

    public function __construct(private int $userId)
    {
    }

    public function handle(): void
    {
        // Load the user and send the email.
    }

    public function queue(): ?string
    {
        return 'emails';
    }

    public function maxAttempts(): int
    {
        return 3;
    }
}
```

Dispatch it:

```php
$queue->push(new SendWelcomeEmailJob($userId));
```

Run only that queue:

```bash
./myxa queue:work emails
```

Inspect only that queue:

```bash
./myxa queue:status emails
./myxa queue:failed emails
```

## Adding Another Backend

If you eventually need cloud-native transports like SQS or RabbitMQ, the framework contracts are already in place. Add a project-level adapter for `QueueInterface` without changing the job API.

## Related Guides

- [Configuration](configuration.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- [Cache](cache.md)
- [Storage](storage.md)
- [Events, Listeners, and Services](events-and-services.md)
