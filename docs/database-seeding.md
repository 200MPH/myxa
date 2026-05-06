# Database Seeding

Seeders load local, demo, fixture, or bootstrap data into application stores.

The project owns seeding conventions, while the framework provides the lower-level database, Redis, Mongo, model, factory, and faker pieces.

## On This Page

- [Common Commands](#common-commands)
- [Seeder Files](#seeder-files)
- [Factory Seeders](#factory-seeders)
- [FakeData Helpers](#fakedata-helpers)
- [Reverse Seeders](#reverse-seeders)
- [Choosing Stores](#choosing-stores)
- [Configuration](#configuration)
- [Further Reading](#further-reading)

## Common Commands

Run the default seeder:

```bash
./myxa db:seed
```

Run a specific seeder:

```bash
./myxa db:seed UserSeeder
./myxa db:seed Demo/UserSeeder
```

Generate a seeder:

```bash
./myxa make:seeder UserSeeder
./myxa make:seeder Demo/UserSeeder
```

Generate a seeder from existing relational database data:

```bash
./myxa make:reverse-seed
./myxa make:reverse-seed --limit=100
./myxa make:reverse-seed --tables=users,posts
./myxa make:reverse-seed --table=users
./myxa make:reverse-seed --table=users --ignore-relations=logs
./myxa make:reverse-seed --connection=mysql
```

Override default store connections for one run:

```bash
./myxa db:seed --connection=mysql
./myxa db:seed --redis-connection=cache
./myxa db:seed --mongo-connection=documents
```

Ask the selected seeder to reset its own target data before seeding:

```bash
./myxa db:seed --truncate
```

`--truncate` runs automatically for seeders that provide a `truncate()` method.

## Seeder Files

Seeder files live in `database/seeders` by default. The root seeder is:

```php
Database\Seeders\DatabaseSeeder
```

Each seeder extends `App\Database\Seeders\Seeder`:

```php
namespace Database\Seeders;

use App\Database\Seeders\SeedContext;
use App\Database\Seeders\Seeder;
use App\Database\Seeders\ShouldTruncate;
use App\Models\Post;
use Myxa\Database\Factory\FakeData;

final class DatabaseSeeder extends Seeder
{
    use ShouldTruncate;

    protected function tablesToTruncate(): array
    {
        return ['posts'];
    }

    public function run(SeedContext $context): void
    {
        $fake = new FakeData();

        for ($index = 0; $index < 10; $index++) {
            Post::create([
                'title' => $fake->sentence(3, 6),
                'body' => $fake->paragraph(3),
                'slug' => $fake->unique('post-slugs')->slug(),
                'status' => $fake->choice(['draft', 'published']),
                'views' => $fake->number(0, 5000),
            ]);
        }
    }
}
```

All models extending `Myxa\Database\Model\Model` support `::create([...])` for declared, non-guarded attributes.
The app boot process wires models to the shared database manager. Calling `$context->database()` first is only
needed when the seeder should honor `db:seed --connection=...` before static model calls.

## Factory Seeders

Use factories for reusable model-shaped records. See [Database Models and Queries](database-models.md#factories)
for defining a model factory.

Use `state()` for seeder-specific factory values. A callback state receives `FakeData`, so generated values can
change for each model:

```php
namespace Database\Seeders;

use App\Database\Seeders\SeedContext;
use App\Database\Seeders\Seeder;
use App\Models\Post;
use Myxa\Database\Factory\FakeData;

final class PublishedPostSeeder extends Seeder
{
    public function run(SeedContext $context): void
    {
        Post::factory($context->database())
            ->count(10)
            ->state(static fn (array $attributes, FakeData $faker): array => [
                'status' => $faker->choice(['draft', 'published']),
                'foo' => $faker->string(),
                'bar' => 123,
            ])
            ->create();
    }
}
```

## FakeData Helpers

Common helpers:

- `string(16)`: random alphanumeric string
- `alpha(12)`: random letters
- `digits(6)`: random numeric string
- `number(1, 100)`: random integer
- `decimal(10, 99, 2)`: random float
- `boolean(25)`: true roughly 25 percent of the time
- `choice(['draft', 'published'])`: pick one value
- `word()`, `words(3)`, `sentence()`, `paragraph()`: text helpers
- `email('example.test')`: generated email address
- `slug(3)`: generated slug
- `unique()->email()`: unique generated value
- `unique('scope-name')->slug()`: unique generated value within a named scope

## Reverse Seeders

`make:reverse-seed` reads live SQL tables and writes a normal PHP seeder under `database/seeders`.
It is intended for local/demo fixtures where developers have already shaped useful relational data by hand.

By default, it writes up to 20 rows from every relational table:

```bash
./myxa make:reverse-seed
```

Use `--tables` for an exact list. It does not discover relations.

Use `--table` for one root table plus directly related tables discovered from foreign keys:

```bash
./myxa make:reverse-seed --tables=users,posts
./myxa make:reverse-seed --table=users
./myxa make:reverse-seed --table=users --ignore-relations=logs
```

Current relation discovery is intentionally shallow: it follows direct incoming and outgoing foreign keys only.
It does not crawl multi-hop relation graphs.

Useful shaping options:

```bash
./myxa make:reverse-seed --limit=100
./myxa make:reverse-seed --exclude-columns=remember_token
./myxa make:reverse-seed --mask=email,name
./myxa make:reverse-seed --override=status=active
./myxa make:reverse-seed --password="local password"
```

`--exclude-columns` removes columns from generated rows.

`--mask` keeps the column but replaces non-null values with deterministic safe fixture values.

`--override=column=value` keeps the column and writes the same value to every generated row.

Use `--password` for credential fixtures. The generator detects columns named `password` or `password_hash` and
the generated seeder hashes the supplied plain password at seed time with `App\Auth\PasswordHasher`.

If `--connection=mysql` is supplied, the generated seeder replays into that named SQL connection. Without it,
the seeder uses the normal `db:seed` connection defaults and any `db:seed --connection=...` override.

Generated reverse seeders include truncation support. Truncation deletes selected tables in child-before-parent
order where foreign keys are visible. If you ignore a related table that still contains rows pointing at a parent
table, your database may reject truncation until those rows are cleared or included.

## Choosing Stores

Use `SeedContext` when a seeder needs to target a specific backing store:

```php
$context->database('mysql')->insert($sql, $bindings);
$context->redis('cache')->set('demo:ready', true);
$context->mongo('documents')->collection('profiles')->insertOne([
    'name' => 'Demo User',
]);
```

When no alias is passed, the context uses `config/seeders.php` defaults or CLI overrides.

`--truncate` is intentionally opt-in per seeder. This keeps destructive behavior local to the seeder that knows which SQL tables, Redis keys, Mongo collections, or external services are safe to reset.

For SQL tables, use the `ShouldTruncate` trait. For other stores, implement `truncate()` directly:

```php
use App\Database\Seeders\SeedContext;
use App\Database\Seeders\Seeder;

final class SearchIndexSeeder extends Seeder
{
    public function truncate(SeedContext $context): void
    {
        $context->redis('cache')->delete('search:index');
    }

    public function run(SeedContext $context): void
    {
        // Seed Redis data...
    }
}
```

## Configuration

Seeder config lives in `config/seeders.php`:

- `path`: where seeder PHP files live
- `namespace`: namespace for generated and discovered seeders
- `default`: root seeder class
- `connections.database`: default SQL connection alias
- `connections.redis`: default Redis connection alias
- `connections.mongo`: default Mongo connection alias

## Further Reading

- [Database](database.md)
- [Database Models and Queries](database-models.md)
- [Database Migrations](database-migrations.md)
- [Mongo Models](mongo-models.md)
- [Cache](cache.md)
