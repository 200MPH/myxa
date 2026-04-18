# Rate Limiting and Throttling

Myxa includes a small request-throttling layer with:

- a shared `RateLimiter`
- route middleware
- named presets in `config/rate_limit.php`
- automatic rate-limit headers on `429 Too Many Requests` responses

In this app, the main entry point is `App\Http\Middleware\ThrottleMiddleware`.

## Use Presets On Routes

The app wrapper middleware resolves named presets from `config/rate_limit.php`:

```php
use App\Http\Middleware\ThrottleMiddleware;
use Myxa\Support\Facades\Route;

Route::middleware([ThrottleMiddleware::using('api')], static function (): void {
    Route::get('/api/reports', [ReportController::class, 'index']);
});
```

The skeleton currently ships with these presets:

- `api`
- `login`
- `uploads`

## Current Presets

The defaults live in:

```text
config/rate_limit.php
```

Out of the box they map to:

- `api`: `60` requests per `60` seconds
- `login`: `5` attempts per `60` seconds
- `uploads`: `20` requests per `60` seconds

Those values can also be overridden from `.env`.

## One-Off Limits

If you need a route-specific limit without creating a preset first, use the framework middleware directly:

```php
use Myxa\Middleware\RateLimitMiddleware;

Route::post('/imports', [ImportController::class, 'store'])
    ->middleware(RateLimitMiddleware::using(10, 60, 'imports'));
```

That means:

- `10` attempts
- per `60` seconds
- stored under the `imports` limiter prefix

## What Happens When A Limit Is Hit

When a request exceeds the configured limit:

- the middleware throws `TooManyRequestsException`
- the app exception handler returns a `429` response
- the response includes rate-limit headers such as:
  - `Retry-After`
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`

That makes the throttling story work for both browsers and APIs.

## Store Backends

The rate-limit store is configured in:

```text
config/rate_limit.php
```

Supported stores in this project:

- `file`
- `redis`

Recommendation:

- use `file` for local development and small single-node apps
- use `redis` when limits must be shared across multiple app nodes

Example production-style env:

```text
RATE_LIMIT_STORE=redis
RATE_LIMIT_REDIS_CONNECTION=default
RATE_LIMIT_REDIS_PREFIX=rate-limit:
```

## Adding Your Own Preset

You can add another named preset in `config/rate_limit.php`:

```php
'presets' => [
    'api' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
        'prefix' => 'api',
    ],
    'admin-reports' => [
        'max_attempts' => 15,
        'decay_seconds' => 60,
        'prefix' => 'admin-reports',
    ],
],
```

Then use it on a route:

```php
Route::get('/admin/reports', [AdminReportController::class, 'index'])
    ->middleware(ThrottleMiddleware::using('admin-reports'));
```

## Good Defaults In Practice

Some reasonable starting points:

- API reads: `60` per minute
- login attempts: `5` per minute
- uploads or expensive writes: `10-20` per minute
- admin-only heavy exports: much lower, often `5-15` per minute

The right values depend on cost, abuse risk, and user experience.

## Throttling vs Queue Backpressure

Rate limiting protects your HTTP surface.
Queues protect background processing.

Use throttling when you want to control request volume at the edge.
Use queues when the work should still happen, just not inline with the request.

Many apps use both:

- throttle `POST /imports`
- enqueue the actual import job

## Related Guides

- [HTTP, Routing, Controllers, and Middleware](http-routing.md)
- [Configuration](configuration.md)
- [Queues](queues.md)
