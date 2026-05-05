# Storage

The app wires the framework storage manager through `App\Providers\StorageServiceProvider` and `config/storage.php`.

Use storage for files: uploads, generated reports, browser-downloadable assets, and S3-compatible object storage.

## Configuration

Storage config lives in:

```text
config/storage.php
```

The default disk is selected by:

```text
STORAGE_DISK=local
```

Current disks:

- `local`: private filesystem storage under `storage/app`
- `public`: browser-accessible filesystem storage under `storage/app/public`
- `db`: database-backed storage
- `s3`: S3-compatible object storage

## Basic Usage

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

## Public and Private Path Prefixes

Use `App\Storage\StorageArea` when you want consistent `public/...` and `private/...` path prefixes on any disk:

```php
use App\Storage\StorageArea;
use Myxa\Support\Facades\Storage;

Storage::put(StorageArea::PublicArea->path('avatars/jane.jpg'), $contents);
Storage::put(StorageArea::PrivateArea->path('reports/invoice-42.pdf'), $contents);
```

That resolves to:

- `public/avatars/jane.jpg`
- `private/reports/invoice-42.pdf`

The helper only builds paths. It does not make a file public or private by itself; visibility still depends on the disk, web server, bucket policy, signed URLs, or application routes that expose the file.

## Local vs Public

Both `local` and `public` are filesystem-backed, but they serve different purposes:

- `local`: private/internal files
- `public`: browser-accessible files

Typical examples:

- `local`: exports, temp files, internal documents
- `public`: avatars, generated PDFs, browser downloads

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

`storage:link` is idempotent when the correct public symlink already exists.

## Uploads

The simplest path is `Request::file(...)->store(...)`:

```php
use Myxa\Support\Facades\Request;

$stored = Request::file('document')->store('documents/report.pdf');
```

A controller version:

```php
use Myxa\Http\Request;

final class DocumentController
{
    public function store(Request $request): array
    {
        $document = $request->file('document');
        $stored = $document->store('documents/' . $document->name());

        return ['location' => $stored->location()];
    }
}
```

`Storage::upload()` is handy when you want the storage facade to handle the upload directly:

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

If you need the original PHP upload payload instead of the normalized `UploadedFile`, use `Request::rawFile()`:

```php
$rawDocument = Request::rawFile('document');
```

For multi-file uploads, loop over `Request::file('documents', [])` and store each `UploadedFile` individually:

```php
use Myxa\Http\Request;

final class ArchiveController
{
    public function store(Request $request): array
    {
        $locations = [];

        foreach ($request->file('documents', []) as $document) {
            if (!$document->isValid()) {
                continue;
            }

            $locations[] = $document->store(
                'archives/' . $document->name(),
                storage: 'public',
            )->location();
        }

        return ['documents' => $locations];
    }
}
```

## S3-Compatible Storage

The `s3` disk uses S3-compatible object storage.

Environment variables:

```text
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_SESSION_TOKEN=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

`AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` are useful for MinIO or other S3-compatible local/dev services.

For scalable apps, a common S3 pattern is one `s3` disk plus the same `App\Storage\StorageArea` helper to build public/private object key prefixes:

```php
use App\Storage\StorageArea;
use Myxa\Support\Facades\Storage;

Storage::put(StorageArea::PublicArea->path('avatars/jane.jpg'), $contents, storage: 's3');
Storage::put(StorageArea::PrivateArea->path('reports/invoice-42.pdf'), $contents, storage: 's3');
```

That resolves to object keys like:

- `public/avatars/jane.jpg`
- `private/reports/invoice-42.pdf`

## Database Storage

The `db` disk uses the framework database-backed storage driver.

That is useful when:

- you want files inside the database
- you need DB-controlled storage metadata

It is usually not the first choice for large public assets.

## Notes

- `Storage::put(...)` without a disk uses the default disk from `config/storage.php`.
- `Storage::put(..., storage: 'public')` is the usual way to create browser-downloadable files.
- Use local/public disks for one-node apps and S3-compatible storage for shared multi-node file storage.

## Related Guides

- [Configuration](configuration.md)
- [HTTP Routing](http-routing.md)
- [Database](database.md)
- `vendor/200mph/myxa-framework/src/Storage/README.md`
