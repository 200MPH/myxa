<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use App\Queue\File\FileQueue;
use App\Queue\InspectableQueueInterface;
use App\Queue\QueueWorker;
use App\Queue\Redis\RedisQueue;
use App\Queue\SerializedJobCodec;
use App\Queue\SimpleRetryPolicy;
use Myxa\Application;
use Myxa\Queue\QueueInterface;
use Myxa\Queue\QueueServiceProvider as FrameworkQueueServiceProvider;
use Myxa\Queue\RetryPolicyInterface;
use Myxa\Queue\WorkerInterface;
use Myxa\Redis\RedisManager;
use Myxa\Support\ServiceProvider;

final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $defaultStore = (string) $config->get('queue.default', 'file');
        $defaultQueue = (string) $config->get('queue.default_queue', 'default');
        $visibilityTimeoutSeconds = (int) $config->get('queue.visibility_timeout_seconds', 60);

        $this->app()->singleton(SerializedJobCodec::class, SerializedJobCodec::class);
        $this->app()->singleton(
            InspectableQueueInterface::class,
            function (Application $app) use (
                $config,
                $defaultQueue,
                $defaultStore,
                $visibilityTimeoutSeconds,
            ): InspectableQueueInterface {
                $storeConfiguration = $config->get(sprintf('queue.stores.%s', $defaultStore), []);
                $driver = is_array($storeConfiguration)
                    ? (string) ($storeConfiguration['driver'] ?? 'file')
                    : 'file';

                return match ($driver) {
                    'redis' => new RedisQueue(
                        connection: $app->make(RedisManager::class)->connection(
                            (string) ($storeConfiguration['connection'] ?? 'default'),
                        ),
                        codec: $app->make(SerializedJobCodec::class),
                        prefix: (string) ($storeConfiguration['prefix'] ?? 'queue:'),
                        defaultQueue: $defaultQueue,
                        visibilityTimeoutSeconds: $visibilityTimeoutSeconds,
                    ),
                    default => new FileQueue(
                        basePath: (string) ($storeConfiguration['path'] ?? storage_path('queue')),
                        codec: $app->make(SerializedJobCodec::class),
                        defaultQueue: $defaultQueue,
                        visibilityTimeoutSeconds: $visibilityTimeoutSeconds,
                    ),
                };
            },
        );

        $this->app()->singleton(SimpleRetryPolicy::class, function () use ($config): SimpleRetryPolicy {
            return new SimpleRetryPolicy(
                defaultMaxAttempts: (int) $config->get('queue.worker.default_max_attempts', 3),
                baseDelaySeconds: (int) $config->get('queue.worker.backoff_seconds', 30),
            );
        });

        $this->app()->singleton(QueueWorker::class, function (Application $app) use ($config): QueueWorker {
            return new QueueWorker(
                queue: $app->make(QueueInterface::class),
                retryPolicy: $app->make(RetryPolicyInterface::class),
                sleepSeconds: (int) $config->get('queue.worker.sleep_seconds', 3),
                maxIdleCycles: (int) $config->get('queue.worker.max_idle_cycles', 0),
            );
        });

        $this->app()->register(new FrameworkQueueServiceProvider(
            queue: static fn (Application $app): QueueInterface => $app->make(InspectableQueueInterface::class),
            worker: static fn (Application $app): WorkerInterface => $app->make(QueueWorker::class),
            retryPolicy: static fn (Application $app): RetryPolicyInterface => $app->make(SimpleRetryPolicy::class),
        ));
    }
}
