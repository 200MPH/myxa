# Database Migrations

Migrations keep SQL schema changes versioned with the app.

## Common Commands

Generate a migration:

```bash
./myxa make:migration create_posts_table --create=posts
```

Run pending migrations:

```bash
./myxa migrate
```

Show status:

```bash
./myxa migrate:status
```

Rollback:

```bash
./myxa migrate:rollback --step=1
```

The migration repository table is created automatically if it does not exist.

## Generating Migrations

`make:migration` creates a timestamped PHP migration file in `database/migrations`.

```bash
./myxa make:migration create_posts_table
```

Options:

- `--create=posts`: generate a create-table migration for `posts`
- `--table=posts`: generate an alter-table migration stub for `posts`
- `--class=CreateBlogPosts`: use an explicit PHP class name instead of deriving it from the migration name
- `--connection=mysql`: add a `connectionName()` method so this migration targets that connection by default

Examples:

```bash
./myxa make:migration create_posts_table --create=posts
./myxa make:migration add_status_to_posts_table --table=posts
./myxa make:migration blog_posts --create=posts --class=CreateBlogPosts
./myxa make:migration create_reports_table --create=reports --connection=analytics
```

Generated create-table migrations include:

```php
$schema->create('posts', function (Blueprint $table): void {
    $table->id();
    $table->timestamps();
});
```

Generated alter-table migrations include TODO blocks in both `up()` and `down()`:

```php
$schema->table('posts', function (Blueprint $table): void {
    // TODO: Define the forward table changes.
});
```

Use `--create` when the migration owns a new table. Use `--table` when it changes an existing table.
Choose one of these options per migration.

## Running Migrations

`migrate` runs every pending migration file and records each successful migration in the repository table.

```bash
./myxa migrate
```

Options:

- `--connection=mysql`: only run migrations whose effective connection is `mysql`

Connection resolution order for `migrate` and `migrate:status`:

1. The migration class `connectionName()` method
2. The command `--connection` option, for migrations that do not define `connectionName()`
3. `config/database.php` default connection

So a migration generated with `--connection=analytics` keeps targeting `analytics`, even if you later run:

```bash
./myxa migrate --connection=mysql
```

In that case, the `analytics` migration is skipped because `--connection=mysql` restricts the run to migrations
whose effective connection is `mysql`.

`migrate:status` shows each migration file, whether it has run, its batch number, and its effective connection.

```bash
./myxa migrate:status
./myxa migrate:status --connection=mysql
```

Options:

- `--connection=mysql`: only show migrations for that effective connection

## Rolling Back

`migrate:rollback` rolls back the latest migration batch for one connection.

```bash
./myxa migrate:rollback
```

Options:

- `--step=2`: roll back the latest 2 batches; defaults to `1`
- `--connection=mysql`: roll back batches recorded for that connection

Examples:

```bash
./myxa migrate:rollback --step=1
./myxa migrate:rollback --step=3 --connection=analytics
```

Rollback calls each migration's `down()` method. If a migration does not implement `down()`, rollback throws.

## Reverse Engineering

`migrate:reverse` generates a create-table migration from an existing live table.

```bash
./myxa migrate:reverse users
```

Options:

- `--connection=mysql`: inspect the table from that source connection
- `--class=CreateImportedUsers`: use an explicit PHP class name in the generated migration

Examples:

```bash
./myxa migrate:reverse users
./myxa migrate:reverse users --connection=mysql
./myxa migrate:reverse audit_logs --connection=legacy --class=CreateAuditLogs
```

This is useful when adopting an existing database into the migration workflow.
The generated file is a normal migration. It is not automatically applied, so review table names, indexes,
foreign keys, defaults, and rollback behavior before running it.

## Schema Snapshots and Diffs

Snapshot the current schema:

```bash
./myxa migrate:snapshot
```

Options:

- `--connection=mysql`: snapshot that connection; defaults to the configured database default
- `--path=database/schema/mysql-before.json`: write the snapshot to a custom JSON file

By default, snapshots are written to `database/schema/{connection}.json`.

Compare a live table to the stored snapshot:

```bash
./myxa migrate:diff users
./myxa migrate:diff users --write
```

Options:

- `--connection=mysql`: inspect the live table from that connection
- `--snapshot=database/schema/mysql-before.json`: compare against a custom snapshot JSON file
- `--class=AlterUsersAfterImport`: use an explicit PHP class name for generated alter migration source
- `--write`: write the generated alter migration file to `database/migrations`

Examples:

```bash
./myxa migrate:snapshot --connection=mysql
./myxa migrate:diff users --connection=mysql
./myxa migrate:diff users --connection=mysql --write
./myxa migrate:diff users --snapshot=database/schema/mysql-before.json --class=AlterImportedUsers --write
```

Without `--write`, `migrate:diff` reports whether changes were detected and tells you to rerun with `--write`.
With `--write`, it creates an alter-table migration from the stored snapshot to the current live table.

## Migration Files

Migrations extend `Myxa\Database\Migrations\Migration` and define `up()` and usually `down()`:

```php
use Myxa\Database\Migrations\Migration;
use Myxa\Database\Schema\Blueprint;
use Myxa\Database\Schema\Schema;

final class CreatePostsTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('posts');
    }
}
```

Optional migration methods:

```php
public function connectionName(): ?string
{
    return 'analytics';
}

public function withinTransaction(): bool
{
    return false;
}
```

`connectionName()` pins a migration to a connection. `withinTransaction()` defaults to `true`; set it to `false` for database operations that cannot run inside a transaction.

Common schema methods available inside migrations:

- `$schema->create('table', fn (Blueprint $table) => ...)`
- `$schema->table('table', fn (Blueprint $table) => ...)`
- `$schema->drop('table')`
- `$schema->dropIfExists('table')`
- `$schema->rename('old_name', 'new_name')`
- `$schema->raw('literal SQL')`
- `$schema->statement('SQL with ?', [$bindings])`

## Model Scaffolding From Existing Sources

Generate from a live table:

```bash
./myxa make:model App\\Models\\AuditLog --from-table=audit_logs
```

Generate from a migration file:

```bash
./myxa make:model App\\Models\\AuditLog --from-migration=2026_04_17_120000_create_audit_logs_table.php
```

Use generated models as a starting point, then review property types, casts, guarded fields, and hidden fields before treating them as final.

## Further Reading

- [Database](database.md)
- [Database Models and Queries](database-models.md)
- [Database Seeding](database-seeding.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- `vendor/200mph/myxa-framework/src/Database/Migrations/README.md`
- `vendor/200mph/myxa-framework/src/Database/Schema/README.md`
