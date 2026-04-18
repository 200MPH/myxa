# Configuration

The app is configured through:

- `.env`
- `config/*.php`
- provider registration in `config/app.php`

`ApplicationFactory` loads all files under `config/` and resolves environment values through `env(...)`.

## Config Files

Current project config files:

- `config/app.php`
- `config/auth.php`
- `config/cache.php`
- `config/database.php`
- `config/maintenance.php`
- `config/migrations.php`
- `config/queue.php`
- `config/rate_limit.php`
- `config/services.php`
- `config/storage.php`
- `config/version.php`

As a rule:

- put defaults and structure in `config/*.php`
- put environment-specific values in `.env`

## Service Providers

The app boots providers from `config/app.php`.

Important providers include:

- `FrameworkServiceProvider`
- `AppServiceProvider`
- `EventServiceProvider`
- `CacheServiceProvider`
- `RoutesServiceProvider`
- `DatabaseServiceProvider`
- `StorageServiceProvider`
- `RateLimitServiceProvider`
- `AuthServiceProvider`
- `RedisServiceProvider`
- `QueueServiceProvider`

If you create a new provider, register it in `config/app.php`.

## Main Environment Variables

## App and HTTP

```text
APP_NAME="Myxa App"
APP_ENV=local
APP_DEBUG=true
APP_URL=https://myxa.localhost
APP_TIMEZONE=UTC
APP_HOST=myxa.localhost
APP_PORT=80
APP_SSL_PORT=443
```

Use these to control:

- application name
- environment mode
- debug behavior
- canonical URL
- timezone
- local hostname and published ports

## Cache

```text
CACHE_STORE=local
CACHE_REDIS_CONNECTION=default
CACHE_REDIS_PREFIX=cache:
ROUTE_CACHE=false
```

`CACHE_STORE` selects the default application cache store from `config/cache.php`.

The cache config currently defines:

- `local` -> file cache
- `redis` -> Redis-backed cache

If you want shared cache across multiple app nodes, switch:

```text
CACHE_STORE=redis
```

`ROUTE_CACHE` controls whether the app should prefer the compiled route manifest when it exists.

Important note:

- routes are not cached until you run `./myxa route:cache`

## Database

```text
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=myxa
DB_USERNAME=myxa
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root
DB_FORWARD_PORT=3306
```

These map into `config/database.php`.

The current skeleton now includes named connection templates for:

- `mysql`
- `pgsql`
- `sqlite`
- `sqlsrv`

`mysql` remains the default because the local Docker stack already boots MySQL out of the box.

If you prefer another engine, switch `DB_CONNECTION` and fill in the matching env values.

## Redis

```text
REDIS_CONNECTION=default
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_DB=0
REDIS_FORWARD_PORT=6379
```

These map into `config/services.php`.

The current default connection alias is:

- `default`

If you want separate Redis connections for cache, queue-like work, or rate limiting, add them in `config/services.php`.

## Queue

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

`config/queue.php` currently defines:

- `file` -> local filesystem queue under `storage/queue`
- `redis` -> shared Redis-backed queue
- a visibility-timeout based reservation recovery path for crashed workers

For local development or a single-node app, `file` is a good default.

For scaled or multi-node deployments, switch to:

```text
QUEUE_CONNECTION=redis
```

`QUEUE_VISIBILITY_TIMEOUT` controls how long a job may stay reserved before it is considered abandoned and moved back to the ready queue.

The worker-related settings map to these behaviors:

- `QUEUE_WORKER_SLEEP` -> seconds to sleep after an empty queue poll
- `QUEUE_WORKER_MAX_IDLE` -> empty-poll limit before the worker exits; `0` means keep running
- `QUEUE_WORKER_MAX_ATTEMPTS` -> default retry limit when a job does not declare its own `maxAttempts()`
- `QUEUE_WORKER_BACKOFF` -> base retry delay in seconds for the default retry policy

The `queue:work` command can override loop behavior such as `--sleep`, `--max-jobs`, `--max-idle`, and `--once`, while retry defaults still come from config.

## Storage

```text
STORAGE_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_SESSION_TOKEN=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

`config/storage.php` currently defines:

- `local` -> `storage/app`
- `public` -> `storage/app/public`
- `db` -> database-backed storage
- `s3` -> S3-compatible object storage

Use:

- `local` for private/internal files
- `public` for web-exposed files
- `s3` for shared or cloud-backed files across multiple nodes

## Rate Limiting

```text
RATE_LIMIT_STORE=file
RATE_LIMIT_REDIS_CONNECTION=default
RATE_LIMIT_REDIS_PREFIX=rate-limit:
RATE_LIMIT_API_MAX_ATTEMPTS=60
RATE_LIMIT_API_DECAY_SECONDS=60
RATE_LIMIT_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_LOGIN_DECAY_SECONDS=60
RATE_LIMIT_UPLOADS_MAX_ATTEMPTS=20
RATE_LIMIT_UPLOADS_DECAY_SECONDS=60
```

`config/rate_limit.php` defines:

- named stores, such as `file` and `redis`
- named presets, such as `api`, `login`, and `uploads`

For a single-server local setup, `file` is fine.

For multi-node or scaled setups, the simplest switch is:

```text
RATE_LIMIT_STORE=redis
```

The extra `RATE_LIMIT_*` values shown above are optional overrides. The app already has sensible defaults in `config/rate_limit.php`.

## Auth

Useful auth-related environment variables include:

```text
AUTH_SESSION_DRIVER=file
AUTH_SESSION_COOKIE=myxa_session
AUTH_SESSION_LIFETIME=1209600
AUTH_SESSION_SAME_SITE=Lax
AUTH_SESSION_SECURE=false
AUTH_SESSION_LENGTH=64
AUTH_SESSION_REDIS_CONNECTION=default
AUTH_SESSION_REDIS_PREFIX=session:
AUTH_TOKEN_LENGTH=40
AUTH_TOKEN_NAME=cli
AUTH_TOKEN_SCOPES=*
```

These live in `config/auth.php`.
They are optional overrides rather than required `.env` entries.

The app currently provides:

- session-based `web` auth
- bearer-token `api` auth
- CLI commands for users and tokens

Session storage drivers:

- `file` -> default and simplest entry point for local development
- `redis` -> recommended for scaled or multi-node deployments
- `database` -> useful when you want inspectable and queryable persisted session rows

## Versioning

The app reads version metadata from `version.json`.

That file is generated with:

```bash
./myxa version:sync
```

The manifest is runtime-friendly and does not require `.git` to be present in production.

## How to Add New Config

1. Add a new `config/*.php` file or extend an existing one.
2. Read values with `env(...)` there.
3. Resolve config through `ConfigRepository` or the `Config` facade in your services.

Example:

```php
use App\Config\ConfigRepository;

final class ReportService
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function timezone(): string
    {
        return (string) $this->config->get('app.timezone', 'UTC');
    }
}
```

## When to Use `.env` vs Config Files

Use `.env` for:

- secrets
- hostnames
- ports
- environment-specific values

Use `config/*.php` for:

- structure
- defaults
- named presets
- feature-level organization

## Further Reading

- [Getting Started](getting-started.md)
- [Events, Listeners, and Services](events-and-services.md)
- [Cache and Storage](cache-and-storage.md)
