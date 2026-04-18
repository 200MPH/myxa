# Queues

The project now wires the framework queue contracts into a real app-level queue layer.

If you have worked with Laravel queues before, this should feel familiar:

- jobs are plain PHP classes with a `handle()` method
- queue selection and retry metadata can live on the job
- workers are managed through CLI commands
- Redis is the recommended shared backend for scaled deployments

The main differences are smaller surface area in the core framework and a simpler validator/model stack overall.

## Config

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

## Supported Queue Stores

- `file` -> local filesystem queue under `storage/queue`
- `redis` -> shared Redis queue for multi-node deployments

Recommended usage:

- local development or small single-node apps -> `file`
- scaled or multi-node apps -> `redis`

`file` is intentionally the simple default.
It is easy to inspect and works well when one machine owns the workload.

`redis` is the better choice when multiple app or worker nodes need to consume the same queue safely.

Both shipped drivers also support reservation recovery. If a worker crashes after reserving a job but before acknowledging it, the job is returned to the ready queue after the visibility timeout expires.

## Job Shape

Basic queued job:

```php
use Myxa\Queue\JobInterface;

final class SendWelcomeEmailJob implements JobInterface
{
    public function __construct(private int $userId)
    {
    }

    public function handle(): void
    {
        // Send the email.
    }
}
```

Job with queue metadata using the `Quable` trait:

```php
use App\Queue\Quable;
use Myxa\Queue\JobInterface;

final class GenerateInvoiceJob implements JobInterface
{
    use Quable;

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

This is the recommended project style:

- always implement `JobInterface`
- add `Quable` when the job wants to declare queue name, delay, or retry defaults

Legacy `QueuedJobInterface` jobs still work, but `JobInterface` plus `Quable` is the simpler app-facing pattern.

## Dispatching Jobs

Inject the queue contract anywhere in the app:

```php
use Myxa\Queue\QueueInterface;

final readonly class SignupService
{
    public function __construct(private QueueInterface $queue)
    {
    }

    public function welcome(int $userId): void
    {
        $this->queue->push(new SendWelcomeEmailJob($userId), [
            'source' => 'signup',
        ]);
    }
}
```

Jobs are serialized before being persisted, so queued jobs should only carry data that can be safely serialized.

## Queue Commands

Process jobs:

```bash
./myxa queue:work
./myxa queue:work emails --once
./myxa queue:work emails --max-jobs=100 --sleep=2
```

Important `queue:work` options:

- `--once` -> process at most one available job, then exit
- `--sleep=<seconds>` -> how long to wait after an empty poll before checking again
- `--max-jobs=<count>` -> stop after processing that many jobs
- `--max-idle=<count>` -> stop after that many empty polls; `0` means keep running

Practical guidance:

- production long-running workers usually leave `--max-idle` at `0`
- `--once` and short `--max-idle` values are most useful in development, CI, or ephemeral worker setups
- `--max-jobs` can be useful in production too when you want workers to recycle periodically

Inspect queues:

```bash
./myxa queue:status
./myxa queue:status emails
```

Inspect and clear failures:

```bash
./myxa queue:failed
./myxa queue:failed emails --limit=20
./myxa queue:retry job-123
./myxa queue:retry-all
./myxa queue:retry-all emails --limit=20
./myxa queue:prune-failed --older-than=7d
./myxa queue:prune-failed emails --older-than=30d
./myxa queue:forget-failed job-123
./myxa queue:flush-failed
./myxa queue:flush-failed emails
```

The failed-job store acts as the project DLQ.

- jobs that exhaust retries are moved there
- operators can inspect them with `queue:failed`
- operators can send one back to the main queue with `queue:retry`
- operators can bulk retry them with `queue:retry-all`
- operators can prune stale failed jobs with `queue:prune-failed`
- operators can permanently delete one with `queue:forget-failed`

Important DLQ command differences:

- `queue:retry <id>` -> retry one failed job
- `queue:retry-all` -> bulk retry failed jobs
- `queue:forget-failed <id>` -> delete one failed job from the DLQ
- `queue:flush-failed` -> delete every failed job, optionally limited to one queue
- `queue:prune-failed --older-than=7d` -> delete only old failed jobs

`queue:prune-failed` supports age suffixes:

- `s` -> seconds
- `m` -> minutes
- `h` -> hours
- `d` -> days
- `w` -> weeks

`QUEUE_VISIBILITY_TIMEOUT` controls how long a reserved job may stay in-flight before it is assumed abandoned and automatically re-queued.

## Real Example: `emails` Queue

You do not need special config just to use a non-default queue name.
Queue names are logical channels chosen by the job or by the caller.

Example job:

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

Dispatch it:

```php
$queue->push(new SendWelcomeEmailJob($userId));
```

Or force the queue name at dispatch time:

```php
$queue->push(new SendWelcomeEmailJob($userId), queue: 'emails');
```

Run only the email worker:

```bash
./myxa queue:work emails
```

A few realistic worker commands:

```bash
./myxa queue:work emails
./myxa queue:work emails --sleep=1
./myxa queue:work emails --max-jobs=500
./myxa queue:work emails --once
```

Inspect only the email queue:

```bash
./myxa queue:status emails
./myxa queue:failed emails
./myxa queue:retry-all emails
./myxa queue:prune-failed emails --older-than=30d
```

## Which Backend Should You Choose?

For this project, the practical answer is:

- `file` first, when the app is small or deployed on one node
- `redis` when you scale to multiple nodes or separate worker processes

If you eventually need cloud-native transports like SQS or RabbitMQ, the framework contracts are already in place and you can add another project-level adapter without changing the job API.
