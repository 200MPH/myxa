# Database

The project wires the SQL database layer through `DatabaseServiceProvider`, `config/database.php`, model generation, and migration commands.

Use this guide for connection setup, raw SQL, and transactions. For model querying, migrations, and document models, see:

- [Database Models and Queries](database-models.md)
- [Database Migrations](database-migrations.md)
- [Database Seeding](database-seeding.md)
- [Mongo Models](mongo-models.md)

Supported SQL engines in the framework today:

- MySQL
- PostgreSQL
- SQLite
- SQL Server

The app skeleton starts with MySQL, but the underlying SQL layer is not MySQL-only.

## On This Page

- [Configuration](#configuration)
- [Raw Queries](#raw-queries)
- [Streaming Raw Results](#streaming-raw-results)
- [Transactions](#transactions)
- [When To Use What](#when-to-use-what)
- [Further Reading](#further-reading)

## Configuration

Primary config lives in:

- `config/database.php`
- `.env`

The default connection is:

```php
'default' => env('DB_CONNECTION', 'mysql')
```

Current local Docker defaults:

```text
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=myxa
DB_USERNAME=myxa
DB_PASSWORD=secret
```

You can add PostgreSQL, SQLite, or SQL Server connections in `config/database.php` when an app needs more than the default MySQL connection.

## Raw Queries

Use the `DB` facade when you want direct SQL:

```php
use Myxa\Support\Facades\DB;

$users = DB::select(
    'SELECT id, email FROM users WHERE status = ? ORDER BY id',
    ['active'],
);
```

Insert:

```php
$userId = DB::insert(
    'INSERT INTO users (email, status) VALUES (?, ?)',
    ['john@example.com', 'active'],
);
```

Update:

```php
DB::update(
    'UPDATE users SET status = ? WHERE id = ?',
    ['inactive', $userId],
);
```

Delete:

```php
DB::delete(
    'DELETE FROM users WHERE id = ?',
    [$userId],
);
```

## Streaming Raw Results

Use `cursor()` when a raw query may return many rows and you do not want to load the full result set into memory:

```php
use Myxa\Support\Facades\DB;

foreach (DB::cursor(
    'SELECT id, email FROM users WHERE status = ? ORDER BY id',
    ['active'],
) as $row) {
    // $row is an associative array.
}
```

For model-backed streaming and batching, see [Large Result Sets, Cursors, and Batching](database-models.md#large-result-sets-cursors-and-batching).

## Transactions

Use `transaction()` when a unit of work should commit or roll back together:

```php
use Myxa\Support\Facades\DB;

DB::transaction(function (): void {
    $userId = DB::insert(
        'INSERT INTO users (email, status) VALUES (?, ?)',
        ['john@example.com', 'active'],
    );

    DB::insert(
        'INSERT INTO profiles (user_id, display_name) VALUES (?, ?)',
        [$userId, 'John'],
    );
});
```

Manual transactions are also available:

```php
DB::beginTransaction();

try {
    DB::update(
        'UPDATE users SET status = ? WHERE id = ?',
        ['inactive', 1],
    );

    DB::commit();
} catch (\Throwable $exception) {
    DB::rollBack();
    throw $exception;
}
```

## When To Use What

- Use `DB::select()`, `insert()`, `update()`, and `delete()` for direct SQL.
- Use `DB::transaction()` around multi-step writes.
- Use `DB::cursor()` for large raw SQL reads.
- Use [Database Models and Queries](database-models.md) for normal app data access.
- Use [Database Migrations](database-migrations.md) to evolve schema.
- Use [Database Seeding](database-seeding.md) to load local, demo, fixture, or bootstrap data.

## Further Reading

- [Database Models and Queries](database-models.md)
- [Database Migrations](database-migrations.md)
- [Database Seeding](database-seeding.md)
- [Mongo Models](mongo-models.md)
- [Configuration](configuration.md)
- `vendor/200mph/myxa-framework/src/Database/README.md`
- `vendor/200mph/myxa-framework/src/Database/Query/README.md`
