# Database Seeding

Seeders load local, demo, fixture, or bootstrap data into application stores.

The project owns seeding conventions, while the framework provides the lower-level database, Redis, Mongo, model, factory, and faker pieces.

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
use App\Models\User;

final class DatabaseSeeder extends Seeder
{
    use ShouldTruncate;

    protected function tablesToTruncate(): array
    {
        return ['users'];
    }

    public function run(SeedContext $context): void
    {
        User::factory($context->database())
            ->count(10)
            ->create();
    }
}
```

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
