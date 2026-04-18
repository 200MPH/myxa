# Cache and Storage

The app wires both the framework cache manager and the framework storage manager through app-level providers and config.

## Cache

Cache config lives in:

```text
config/cache.php
```

The default app cache store is selected by:

```text
CACHE_STORE=local
CACHE_REDIS_CONNECTION=default
CACHE_REDIS_PREFIX=cache:
```

## Basic Cache Usage

Using the facade:

```php
use Myxa\Support\Facades\Cache;

Cache::put('users.count', 15);
$count = Cache::get('users.count');

Cache::remember('dashboard.stats', fn () => ['ready' => true], 300);
Cache::forget('users.count');
```

TTL values are in seconds.

## Cache Commands

Clear the whole configured store:

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

`cache:forget` is targeted invalidation.

`cache:clear` is a full store flush.

The app now ships both:

- `local` -> file cache under `storage/cache`
- `redis` -> Redis-backed cache using the configured Redis connection

For a single-node local setup, `local` is fine.

For multi-node or scaled setups, switch to Redis with:

```text
CACHE_STORE=redis
```

Optional Redis cache tuning:

- `CACHE_REDIS_CONNECTION`
- `CACHE_REDIS_PREFIX`

## Route Cache

Route caching is separate from application cache values.

Build it:

```bash
./myxa route:cache
```

Clear it:

```bash
./myxa route:clear
```

Routes are not cached until you run `route:cache`.

## Storage

Storage config lives in:

```text
config/storage.php
```

Current disks:

- `local` -> `storage/app`
- `public` -> `storage/app/public`
- `db` -> database-backed storage
- `s3` -> S3-compatible object storage

The default disk is selected by:

```text
STORAGE_DISK=local
```

S3 environment variables:

```text
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_SESSION_TOKEN=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

`AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` are especially useful for MinIO or other S3-compatible local/dev services.

## Local vs Public

Both `local` and `public` are filesystem-backed, but they serve different purposes:

- `local`: private/internal files
- `public`: browser-accessible files

Typical examples:

- `local`: exports, temp files, internal documents
- `public`: avatars, generated PDFs, browser downloads

## Basic Storage Usage

Using the facade:

```php
use Myxa\Support\Facades\Storage;

Storage::put('reports/test.txt', 'hello');
$contents = Storage::read('reports/test.txt');
$exists = Storage::exists('reports/test.txt');
Storage::delete('reports/test.txt');
```

Write to a specific disk:

```php
Storage::put('pdf/invoice-1.pdf', $binary, storage: 'public');
```

Using the manager directly:

```php
use Myxa\Storage\StorageManager;

final class ReportController
{
    public function store(StorageManager $storage): string
    {
        $storage->put('reports/test.txt', 'hello', storage: 'local');

        return $storage->read('reports/test.txt', 'local');
    }
}
```

## Uploads

Example upload:

```php
use Myxa\Support\Facades\Request;
use Myxa\Support\Facades\Storage;

$stored = Storage::upload(
    Request::file('document'),
    'documents',
    ['allowed_extensions' => ['pdf']],
    'public',
);
```

## Public File URLs

To expose the `public` disk, create the symlink:

```bash
./myxa storage:link
```

That creates:

```text
public/storage -> storage/app/public
```

If you save a file to:

```php
Storage::put('reports/file.pdf', $pdfBinary, storage: 'public');
```

the browser URL becomes:

```text
https://myxa.localhost/storage/reports/file.pdf
```

Important:

- use `/storage/...`
- do not use `/public/storage/...`

## Database Storage

The `db` disk uses the framework database-backed storage driver.

That is useful when:

- you want files inside the database
- you need DB-controlled storage metadata

It is usually not the first choice for large public assets.

## Scaled Systems

Cache:

- single node -> file cache is fine
- multi-node -> prefer Redis-backed cache

Rate limiting:

- single node -> file store is fine
- multi-node -> prefer Redis-backed rate-limit store

Storage:

- local/public disks are fine for one node
- shared or cloud-backed storage is better for multi-node systems

The current framework already ships:

- local storage
- database storage
- S3-compatible storage

## Notes

- `Storage::put(...)` without a disk uses the default disk from `config/storage.php`.
- `Storage::put(..., storage: 'public')` is the usual way to create browser-downloadable files.
- `storage:link` is idempotent when the correct public symlink already exists.

## Further Reading

- `vendor/200mph/myxa-framework/src/Cache/README.md`
- `vendor/200mph/myxa-framework/src/Storage/README.md`
- [Configuration](configuration.md)
