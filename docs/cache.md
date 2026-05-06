# Cache

The app wires the framework cache manager through `App\Providers\CacheServiceProvider` and `config/cache.php`.

Use cache for short-lived computed values, lookup results, or other data that can be safely rebuilt.

## On This Page

- [Configuration](#configuration)
- [Stores](#stores)
- [Basic Usage](#basic-usage)
- [Commands](#commands)
- [Route Cache](#route-cache)
- [Notes](#notes)
- [Related Guides](#related-guides)

## Configuration

Cache config lives in:

```text
config/cache.php
```

The default cache store is selected by:

```text
CACHE_STORE=local
```

Redis cache settings:

```text
CACHE_REDIS_CONNECTION=default
CACHE_REDIS_PREFIX=cache:
```

## Stores

The app ships with:

- `local`: file cache under `storage/cache`
- `redis`: Redis-backed cache using the configured Redis connection

Recommended usage:

- use `local` for development and small single-node apps
- use `redis` when multiple app nodes need to share cached values

## Basic Usage

Using the facade:

```php
use Myxa\Support\Facades\Cache;

Cache::put('users.count', 15);
$count = Cache::get('users.count');

Cache::remember('dashboard.stats', fn () => ['ready' => true], 300);
Cache::forget('users.count');
```

TTL values are in seconds.

Use a named store when needed:

```php
Cache::put('reports.ready', true, ttl: 300, store: 'redis');
```

## Commands

Clear the configured store:

```bash
./myxa cache:clear
```

Clear a named store:

```bash
./myxa cache:clear --store=local
```

Forget one key:

```bash
./myxa cache:forget users:123
./myxa cache:forget dashboard:stats --store=local
```

Use `cache:forget` for targeted invalidation.
Use `cache:clear` for a full store flush.

## Route Cache

Route caching is separate from application cache values.

Build the route cache:

```bash
./myxa route:cache
```

Clear it:

```bash
./myxa route:clear
```

Routes are not cached until you run `route:cache`.

## Notes

- Cache values should be safe to lose and rebuild.
- Prefer explicit TTLs for data that can become stale.
- Use Redis for shared cache state across multiple app nodes.
- Route cache and application cache are separate systems.

## Related Guides

- [Configuration](configuration.md)
- [HTTP Routing](http-routing.md)
- [Rate Limiting and Throttling](rate-limiting.md)
- `vendor/200mph/myxa-framework/src/Cache/README.md`
