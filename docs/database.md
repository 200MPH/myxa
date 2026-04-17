# Database, Query Builder, Models, and Migrations

The project already wires the framework database layer through `DatabaseServiceProvider`, model generation, and migration commands.

Supported SQL engines in the framework today:

- MySQL
- PostgreSQL
- SQLite
- SQL Server

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

This skeleton starts with a MySQL connection, but the underlying SQL layer is not MySQL-only. If you need them, you can add PostgreSQL, SQLite, or SQL Server connections in `config/database.php` as well.

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

## ModelQuery Examples

For this project, the most common happy path is to work through models first and drop to raw SQL or the lower-level query builder only when you really need to.

Basic filtering:

```php
$users = User::query()
    ->where('status', '=', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

Find one record:

```php
$user = User::query()
    ->where('email', '=', 'john@example.com')
    ->first();
```

Require one record:

```php
$user = User::query()->findOrFail(1);
```

Existence checks:

```php
$exists = User::query()
    ->where('email', '=', 'john@example.com')
    ->exists();
```

Simple pagination-like slicing:

```php
$posts = Post::query()
    ->orderBy('id', 'DESC')
    ->limit(20, 40)
    ->get();
```

Eager loading:

```php
$users = User::query()
    ->with('posts', 'sessions')
    ->orderBy('id')
    ->get();
```

Relationship query:

```php
$user = User::findOrFail(1);

$posts = $user->posts()
    ->where('published', '=', 1)
    ->orderBy('id', 'DESC')
    ->get();
```

Nested eager loading:

```php
$users = User::query()
    ->with('posts.comments')
    ->get();
```

Join example:

```php
$users = User::query()
    ->select('users.id', 'users.email', 'profiles.display_name')
    ->join('profiles', 'profiles.user_id', '=', 'users.id')
    ->where('users.status', '=', 'active')
    ->orderBy('users.id', 'DESC')
    ->get();
```

More advanced join clauses are available too:

```php
$users = User::query()
    ->select('users.id', 'profiles.display_name')
    ->leftJoin('profiles', static function ($join): void {
        $join->on('profiles.user_id', '=', 'users.id')
            ->where('profiles.status', '=', 1);
    })
    ->get();
```

This guide keeps the lower-level query builder examples light on purpose because the framework documentation already covers that layer in detail.

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

## Declared Properties Are the Model Contract

Myxa models are strict. Persisted fields should be declared as real PHP properties on the model class.

That means these properties are not just hints for the reader, they are the normal writable attribute contract for the model.

Good examples:

```php
protected string $email = '';
protected ?string $name = null;
protected ?int $user_id = null;
```

Practical rules:

- if a field belongs to the model, declare it
- if a field may be missing, make it nullable or give it a sensible default
- if you use typed properties without defaults, make sure your code initializes them before relying on them
- metadata properties like `$table`, `$primaryKey`, and `$connection` are separate from normal persisted attributes

Normal writes are strict:

- `fill([...])` accepts only declared, non-guarded properties
- `setAttribute()` accepts only declared model properties
- `$model->property = ...` follows the same rule
- unknown attributes throw an exception during normal writes

So if you forget to declare a model property, it is not treated as a normal writable attribute.

## Guarded, Hidden, and Internal Attributes

The framework supports attribute metadata on model properties:

```php
use Myxa\Database\Attributes\Guarded;
use Myxa\Database\Attributes\Hidden;
use Myxa\Database\Attributes\Internal;

final class User extends Model
{
    protected string $table = 'users';

    protected ?int $id = null;
    protected string $email = '';

    #[Guarded]
    #[Hidden]
    protected ?string $password_hash = null;

    #[Internal]
    protected string $helperLabel = 'draft';
}
```

Behavior:

- `#[Guarded]` skips the property during `fill([...])`
- `#[Hidden]` excludes it from `toArray()` and JSON serialization
- `#[Internal]` removes it from normal persisted model field handling entirely

That is useful for things like password hashes, helper state, computed flags, or other non-persisted internals.

## Declared Fields vs Extra Hydrated Columns

Normal writes are strict, but hydrated rows may still contain additional columns from trusted storage data.

For example, computed selects or joined aliases can still exist on a hydrated model:

```php
$user = User::hydrate([
    'id' => 1,
    'email' => 'john@example.com',
    'computed_label' => 'Admin',
]);

$user->getAttribute('computed_label'); // 'Admin'
```

Important distinction:

- declared properties are the normal writable model fields
- extra hydrated attributes can still exist on trusted loaded data
- those extra values are available through `getAttribute()`
- they may appear in serialization unless hidden
- they are not part of the normal declared writable model contract

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

Count and existence examples:

```php
$hasPosts = Post::query()
    ->where('user_id', '=', 1)
    ->exists();

$count = count(Post::query()
    ->where('user_id', '=', 1)
    ->get());
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

## MongoModel

The framework also includes `MongoModel` for document-backed models.

Use SQL `Model` when you are working with relational tables.

Use `MongoModel` when you want declared-property models backed by Mongo-style collections instead of SQL tables.

Basic example:

```php
use Myxa\Mongo\MongoModel;

final class UserDocument extends MongoModel
{
    protected string $collection = 'users';

    // Mongo uses _id by default.
    protected string|int|null $_id = null;

    protected string $email = '';
    protected string $status = '';
}
```

The same strict declared-property idea still applies:

- document fields should be declared as real properties
- unknown attributes are rejected during normal writes
- guarded, hidden, internal, and cast attributes still apply

Typical usage:

```php
UserDocument::setManager($mongoManager);

$user = UserDocument::create([
    'email' => 'john@example.com',
    'status' => 'active',
]);

$found = UserDocument::find($user->getKey());
$found->status = 'inactive';
$found->save();
```

Important difference from SQL models:

- `MongoModel` is document-backed
- it does not use the SQL query builder
- it does not provide SQL-style relations like `hasMany()` or `belongsTo()`

Connection support today:

- `MongoManager` resolves named Mongo connections
- each `MongoConnection` resolves named collections
- the built-in collection implementation currently shipped by the framework is `InMemoryMongoCollection`
- custom collection backends can be added by implementing `MongoCollectionInterface`

So at the moment, Mongo support is best described as:

- a document-model layer with a connection/collection abstraction
- built-in in-memory support for tests and local experiments
- room for custom adapters when you want a real external Mongo backend

This project does not scaffold Mongo connections yet, so treat this as a framework capability you can wire in when your app needs document storage.

## Notes

- Prefer declared properties on models. They are the real attribute contract, not just documentation.
- Unknown attributes are rejected during normal writes such as `fill()` and `setAttribute()`.
- Use nullable properties or defaults for fields that may be absent.
- Use `#[Guarded]`, `#[Hidden]`, and `#[Internal]` deliberately so model serialization and persistence stay predictable.
- Use the query builder when you want SQL generation but not a full model.
- Use reverse-engineering commands as bootstrap tools, then continue migration-first.

## Further Reading

- `vendor/200mph/myxa-framework/src/Database/README.md`
- `vendor/200mph/myxa-framework/src/Database/Query/README.md`
- `vendor/200mph/myxa-framework/src/Database/Model/README.md`
- `vendor/200mph/myxa-framework/src/Mongo/README.md`
- [Console and Scaffolding](console-and-scaffolding.md)
