# Database, Query Builder, Models, and Migrations

The project already wires the framework database layer through `DatabaseServiceProvider`, model generation, and migration commands.

## Database Configuration

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

## Raw Queries with `DB`

Example select:

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

## Query Builder

Use the fluent builder when you want generated SQL and bindings:

```php
use Myxa\Support\Facades\DB;

$query = DB::query()
    ->select('id', 'email')
    ->from('users')
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10);

$rows = DB::select($query->toSql(), $query->getBindings());
```

Join example:

```php
$query = DB::query()
    ->select('u.id', 'p.user_id')
    ->from('users as u')
    ->join('profiles as p', static function ($join): void {
        $join->on('u.id', '=', 'p.user_id')
            ->where('p.status', '=', 1);
    });
```

## Transactions

Automatic transaction:

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

Manual transaction:

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

## Models

Models live under:

```text
app/Models
```

Generate one:

```bash
./myxa make:model App\\Models\\Post
```

Basic example:

```php
use Myxa\Database\Model\HasTimestamps;
use Myxa\Database\Model\Model;

final class Post extends Model
{
    use HasTimestamps;

    protected string $table = 'posts';

    protected ?int $id = null;
    protected string $title = '';
    protected string $body = '';
    protected ?int $user_id = null;
}
```

Basic actions:

```php
$post = Post::create([
    'title' => 'Hello',
    'body' => 'World',
    'user_id' => 1,
]);

$found = Post::find(1);
$all = Post::all();

$post->title = 'Updated';
$post->save();

$post->delete();
```

Query through the model:

```php
$posts = Post::query()
    ->where('user_id', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

## Relationships

Example relations:

```php
final class User extends Model
{
    protected string $table = 'users';
    protected ?int $id = null;
    protected string $email = '';

    public function posts(): \Myxa\Database\Model\ModelQuery
    {
        return $this->hasMany(Post::class);
    }
}

final class Post extends Model
{
    protected string $table = 'posts';
    protected ?int $id = null;
    protected ?int $user_id = null;
    protected string $title = '';

    public function user(): \Myxa\Database\Model\ModelQuery
    {
        return $this->belongsTo(User::class);
    }
}
```

Eager loading:

```php
$users = User::query()
    ->with('posts')
    ->orderBy('id')
    ->get();
```

## Migrations

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

## Reverse Engineering and Schema Diffing

Snapshot the current schema:

```bash
./myxa migrate:snapshot
```

Compare a live table to the stored snapshot:

```bash
./myxa migrate:diff users
./myxa migrate:diff users --write
```

Generate a create migration from an existing table:

```bash
./myxa migrate:reverse users
```

This is especially useful when adopting an existing database into the migration workflow.

## Model Scaffolding from Existing Sources

Generate from a live table:

```bash
./myxa make:model App\\Models\\AuditLog --from-table=audit_logs
```

Generate from a migration file:

```bash
./myxa make:model App\\Models\\AuditLog --from-migration=2026_04_17_120000_create_audit_logs_table.php
```

## Notes

- Prefer declared properties on models. Unknown attributes are rejected during normal writes.
- Use the query builder when you want SQL generation but not a full model.
- Use reverse-engineering commands as bootstrap tools, then continue migration-first.

## Further Reading

- `vendor/200mph/myxa-framework/src/Database/README.md`
- `vendor/200mph/myxa-framework/src/Database/Query/README.md`
- `vendor/200mph/myxa-framework/src/Database/Model/README.md`
- [Console and Scaffolding](console-and-scaffolding.md)
