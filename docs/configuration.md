# Configuration

The app is configured through:

- `.env`
- `config/*.php`
- provider registration in `config/app.php`

`ApplicationFactory` loads all files under `config/` and resolves environment values through `env(...)`.

## Config Files

Current project config files:

- `config/app.php`: app identity, bootstrap defaults, base URL, timezone, log path, and provider registration.
- `config/auth.php`: authentication, session storage, token storage, guards, and auth infrastructure defaults.
- `config/cache.php`: application cache stores, cache prefixes, and route cache settings.
- `config/database.php`: database connections for MySQL, PostgreSQL, SQLite, and SQL Server.
- `config/maintenance.php`: maintenance-mode state handling, wait behavior, and command allowlists.
- `config/migrations.php`: migration paths, schema snapshots, repository table names, and model-generation paths.
- `config/queue.php`: queue driver, named stores, worker behavior, retry defaults, visibility timeout, and Redis queue options.
- `config/rate_limit.php`: rate-limit stores and reusable presets such as `api`, `login`, and `uploads`.
- `config/services.php`: infrastructure-style service connections, mainly Redis by default.
- `config/storage.php`: default storage disk and named local, public, database-backed, or S3-backed disks.
- `config/version.php`: version metadata source, sync behavior, and generated version file location.

As a rule:

- put defaults and structure in `config/*.php`
- put environment-specific values in `.env`

## Service Providers

The app boots providers from `config/app.php`.

Important providers include:

- `FrameworkServiceProvider`: registers core framework services such as requests, responses, routing, and validation.
- `AppServiceProvider`: registers app-level services such as HTML rendering, exception handling, logging, and timezone setup.
- `EventServiceProvider`: registers the event bus and the application's event-to-listener map.
- `CacheServiceProvider`: builds configured file or Redis cache stores and selects the default cache store.
- `RoutesServiceProvider`: loads cached routes when enabled, otherwise loads route files from source.
- `DatabaseServiceProvider`: registers configured PDO database connections and the default connection.
- `StorageServiceProvider`: registers configured local, database-backed, and S3-backed storage disks.
- `RateLimitServiceProvider`: registers the rate limiter store and framework rate-limiting services.
- `AuthServiceProvider`: registers auth config, password hashing, users, tokens, sessions, guards, and token resolvers.
- `RedisServiceProvider`: registers configured Redis connections and the default Redis connection.
- `QueueServiceProvider`: registers queue storage, retry policy, and worker services.

If you create a new provider, register it in `config/app.php`.

## Main Environment Variables

The sections below cover the environment variables you are most likely to change in a fresh app.

### App and HTTP

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

Variable notes:

- `APP_NAME`: display name for the application.
- `APP_ENV`: current runtime environment, such as `local`, `staging`, or `production`.
- `APP_DEBUG`: enables detailed error output when set to `true`.
- `APP_URL`: canonical base URL used when the app needs to build absolute links.
- `APP_TIMEZONE`: default PHP timezone used by the application.
- `APP_HOST`: local hostname used by the Docker nginx configuration.
- `APP_PORT`: host HTTP port published by Docker.
- `APP_SSL_PORT`: host HTTPS port published by Docker.

### Cache

```text
CACHE_STORE=local
CACHE_REDIS_CONNECTION=default
CACHE_REDIS_PREFIX=cache:
ROUTE_CACHE=false
```

Variable notes:

- `CACHE_STORE`: default application cache store, usually `local` or `redis`.
- `CACHE_REDIS_CONNECTION`: Redis connection name to use when `CACHE_STORE=redis`.
- `CACHE_REDIS_PREFIX`: key prefix used for Redis-backed cache entries.
- `ROUTE_CACHE`: tells the app to prefer the compiled route manifest when it exists.

`config/cache.php` defines these stores:

- `local`: file cache
- `redis`: Redis-backed cache

If you want shared cache across multiple app nodes, switch:

```text
CACHE_STORE=redis
```

Important note:

- routes are not cached until you run `./myxa route:cache`

### Database

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

Variable notes:

- `DB_CONNECTION`: default database connection name.
- `DB_HOST`: database host for the default MySQL connection.
- `DB_PORT`: database port for the default MySQL connection.
- `DB_DATABASE`: database name for the default MySQL connection.
- `DB_USERNAME`: database username for the default MySQL connection.
- `DB_PASSWORD`: database password for the default MySQL connection.
- `DB_ROOT_PASSWORD`: root password used by the local Docker MySQL service.
- `DB_FORWARD_PORT`: host port published by Docker for MySQL.

`config/database.php` includes named connection templates for:

- `mysql`
- `pgsql`
- `sqlite`
- `sqlsrv`

`mysql` remains the default because the local Docker stack already boots MySQL out of the box.

If you prefer another engine, switch `DB_CONNECTION` and fill in the matching env values.

### Redis

```text
REDIS_CONNECTION=default
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_DB=0
REDIS_FORWARD_PORT=6379
```

Variable notes:

- `REDIS_CONNECTION`: default Redis connection name.
- `REDIS_HOST`: Redis host for the default connection.
- `REDIS_PORT`: Redis port for the default connection.
- `REDIS_DB`: Redis database index for the default connection.
- `REDIS_FORWARD_PORT`: host port published by Docker for Redis.

These map into `config/services.php`. The default connection alias is `default`.

If you want separate Redis connections for cache, queues, sessions, or rate limiting, add them in `config/services.php`.

### Queue

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

Variable notes:

- `QUEUE_CONNECTION`: default queue store, usually `file` or `redis`.
- `QUEUE_NAME`: default queue name used when a job does not specify one.
- `QUEUE_VISIBILITY_TIMEOUT`: seconds a reserved job may stay hidden before being released back to the queue.
- `QUEUE_REDIS_CONNECTION`: Redis connection name to use when `QUEUE_CONNECTION=redis`.
- `QUEUE_REDIS_PREFIX`: key prefix used for Redis-backed queues.
- `QUEUE_WORKER_SLEEP`: seconds to sleep after an empty queue poll.
- `QUEUE_WORKER_MAX_IDLE`: empty-poll limit before the worker exits; `0` means keep running.
- `QUEUE_WORKER_MAX_ATTEMPTS`: default retry limit when a job does not declare its own `maxAttempts()`.
- `QUEUE_WORKER_BACKOFF`: base retry delay in seconds for the default retry policy.

`config/queue.php` defines:

- `file`: local filesystem queue under `storage/queue`
- `redis`: shared Redis-backed queue
- a visibility-timeout based reservation recovery path for crashed workers

For local development or a single-node app, `file` is a good default.

For scaled or multi-node deployments, switch to:

```text
QUEUE_CONNECTION=redis
```

The `queue:work` command can override loop behavior such as `--sleep`, `--max-jobs`, `--max-idle`, and `--once`, while retry defaults still come from config.

### Storage

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

Variable notes:

- `STORAGE_DISK`: default storage disk, usually `local`, `public`, `db`, or `s3`.
- `AWS_ACCESS_KEY_ID`: access key for S3-compatible storage.
- `AWS_SECRET_ACCESS_KEY`: secret key for S3-compatible storage.
- `AWS_DEFAULT_REGION`: region used by the S3-compatible storage client.
- `AWS_BUCKET`: bucket name used by the `s3` disk.
- `AWS_ENDPOINT`: optional custom endpoint for S3-compatible providers.
- `AWS_SESSION_TOKEN`: optional temporary session token for S3 credentials.
- `AWS_USE_PATH_STYLE_ENDPOINT`: enables path-style S3 URLs for providers that require them.

`config/storage.php` defines:

- `local`: `storage/app`
- `public`: `storage/app/public`
- `db`: database-backed storage
- `s3`: S3-compatible object storage

Choose disks by use case:

- `local` for private/internal files
- `public` for web-exposed files
- `s3` for shared or cloud-backed files across multiple nodes

Use `App\Storage\StorageArea` when you want consistent `public/...` and `private/...` path prefixes. The helper can be used with any disk; for S3-backed apps, a common pattern is one `s3` disk plus those prefixes.

### Rate Limiting

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

Variable notes:

- `RATE_LIMIT_STORE`: default rate-limit store, usually `file` for local use or `redis` for shared deployments.
- `RATE_LIMIT_REDIS_CONNECTION`: Redis connection name to use when `RATE_LIMIT_STORE=redis`.
- `RATE_LIMIT_REDIS_PREFIX`: key prefix used for Redis-backed rate-limit counters.
- `RATE_LIMIT_API_MAX_ATTEMPTS`: maximum attempts allowed for the `api` preset.
- `RATE_LIMIT_API_DECAY_SECONDS`: time window, in seconds, for the `api` preset.
- `RATE_LIMIT_LOGIN_MAX_ATTEMPTS`: maximum attempts allowed for the `login` preset.
- `RATE_LIMIT_LOGIN_DECAY_SECONDS`: time window, in seconds, for the `login` preset.
- `RATE_LIMIT_UPLOADS_MAX_ATTEMPTS`: maximum attempts allowed for the `uploads` preset.
- `RATE_LIMIT_UPLOADS_DECAY_SECONDS`: time window, in seconds, for the `uploads` preset.

`config/rate_limit.php` defines:

- named stores, such as `file` and `redis`
- named presets, such as `api`, `login`, and `uploads`

For a single-server local setup, `file` is fine.

For multi-node or scaled setups, the simplest switch is:

```text
RATE_LIMIT_STORE=redis
```

The extra `RATE_LIMIT_*` values shown above are optional overrides. The app already has sensible defaults in `config/rate_limit.php`.

### Auth

Common auth-related environment variables:

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

Variable notes:

- `AUTH_SESSION_DRIVER`: session storage driver, usually `file`, `redis`, or `database`.
- `AUTH_SESSION_COOKIE`: cookie name used for the web session.
- `AUTH_SESSION_LIFETIME`: session lifetime in seconds.
- `AUTH_SESSION_SAME_SITE`: controls when browsers send the session cookie on cross-site requests. `Lax` is a good default for normal apps, `Strict` is more restrictive and may block cookies after cross-site navigation, and `None` allows cross-site cookie use but requires `AUTH_SESSION_SECURE=true`.
- `AUTH_SESSION_SECURE`: sends the session cookie only over HTTPS when set to `true`.
- `AUTH_SESSION_LENGTH`: generated session token length.
- `AUTH_SESSION_REDIS_CONNECTION`: Redis connection name to use when `AUTH_SESSION_DRIVER=redis`.
- `AUTH_SESSION_REDIS_PREFIX`: key prefix used for Redis-backed sessions.
- `AUTH_TOKEN_LENGTH`: generated bearer token length.
- `AUTH_TOKEN_NAME`: default token name used by token-related CLI commands.
- `AUTH_TOKEN_SCOPES`: default token scopes used by token-related CLI commands.

These live in `config/auth.php`.
They are optional overrides rather than required `.env` entries.

The app provides:

- session-based `web` auth
- bearer-token `api` auth
- CLI commands for users and tokens

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
3. Resolve config through `ConfigRepository` in your services.

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
- [Cache](cache.md)
- [Storage](storage.md)
