# Database Models and Queries

For most app code, start with models and model queries. Drop to raw SQL only when a query is easier to express directly.

Models live in:

```text
app/Models
```

Generate one:

```bash
./myxa make:model App\\Models\\Post
```

## On This Page

- [Basic Model](#basic-model)
- [Factories](#factories)
- [Querying Models](#querying-models)
- [Large Result Sets, Cursors, and Batching](#large-result-sets-cursors-and-batching)
- [Joins](#joins)
- [Relationships](#relationships)
- [Declared Properties](#declared-properties)
- [Guarded, Hidden, and Internal Attributes](#guarded-hidden-and-internal-attributes)
- [Casting](#casting)
- [Extra Hydrated Columns](#extra-hydrated-columns)
- [Further Reading](#further-reading)

## Basic Model

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

## Factories

Factories create fake model data for tests, demos, local tooling, and seeders.
The framework provides the base `Factory`, `FakeData`, and `HasFactory`; the app owns concrete factories.

Define a factory under an autoloaded app namespace such as `App\Database\Factories`:

```php
namespace App\Database\Factories;

use App\Models\Post;
use Myxa\Database\Factory\Factory;
use Myxa\Database\Model\Model;

final class PostFactory extends Factory
{
    protected function model(): string
    {
        return Post::class;
    }

    protected function definition(): array
    {
        return [
            'title' => $this->faker()->sentence(3, 6),
            'body' => $this->faker()->paragraph(3),
            'slug' => $this->faker()->unique('post-slugs')->slug(),
            'status' => $this->faker()->choice(['draft', 'published']),
            'views' => $this->faker()->number(0, 5000),
        ];
    }

    protected function afterCreating(Model $model): void
    {
        // Optional hook for related records or side effects.
    }
}
```

Attach it to the model with `HasFactory`:

```php
use App\Database\Factories\PostFactory;
use Myxa\Database\Factory\Factory;
use Myxa\Database\Model\HasFactory;
use Myxa\Database\Model\Model;

final class Post extends Model
{
    use HasFactory;

    protected string $table = 'posts';

    protected ?int $id = null;
    protected string $title = '';
    protected string $body = '';
    protected string $slug = '';
    protected string $status = 'draft';
    protected int $views = 0;

    protected static function newFactory(): Factory
    {
        return PostFactory::new();
    }
}
```

Then use either the factory class directly or the model facade-style helper:

```php
$post = PostFactory::new()->make();
$post = Post::factory()->make();
```

Build modes:

```php
$attributes = Post::factory()->raw();
$post = Post::factory()->make();
$persisted = Post::factory()->create();

$posts = Post::factory()
    ->count(10)
    ->create();
```

What each method does:

- `raw()` returns the final attribute array without creating a model
- `make()` returns an unsaved model instance
- `create()` returns a saved model instance
- `count(10)` repeats the operation and returns a list

Use `state()` for reusable variants, and pass attributes to `raw()`, `make()`, or `create()` for one-off overrides:

```php
$published = Post::factory()
    ->state(['status' => 'published'])
    ->create([
        'title' => 'Release notes',
    ]);
```

Factory values are resolved in this order:

1. `definition()`
2. each `state(...)` call
3. final overrides passed to `raw()`, `make()`, or `create()`

Factories use `FakeData` through `$this->faker()`:

```php
[
    'code' => $this->faker()->string(16),
    'email' => $this->faker()->unique()->email(),
    'slug' => $this->faker()->unique('post-slugs')->slug(),
    'score' => $this->faker()->decimal(1, 5, 1),
    'is_public' => $this->faker()->boolean(80),
]
```

## Querying Models

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

Pagination-like slicing:

```php
$posts = Post::query()
    ->orderBy('id', 'DESC')
    ->limit(20, 40)
    ->get();
```

## Large Result Sets, Cursors, and Batching

Use `cursor()` to stream models one at a time:

```php
foreach (User::query()
    ->where('status', '=', 'active')
    ->orderBy('id')
    ->cursor() as $user) {
    // $user is a hydrated User model.
}
```

You can also stream directly from the model, with optional limit and offset arguments:

```php
foreach (User::cursor(limit: 500) as $user) {
    // Handle one model at a time.
}
```

Use `chunk()` when your work is naturally batch-oriented:

```php
User::query()
    ->where('status', '=', 'active')
    ->orderBy('id')
    ->chunk(100, function (array $users, int $page): void {
        foreach ($users as $user) {
            // Handle a batch of hydrated User models.
        }
    });
```

Return `false` from the chunk callback to stop early:

```php
$completed = User::chunk(100, function (array $users, int $page): bool {
    // Stop after the first batch.
    return false;
});

// $completed === false
```

Use a stable `orderBy()` when streaming or chunking records so processing is predictable.

## Joins

Simple join:

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
    ->with('posts', 'sessions')
    ->orderBy('id')
    ->get();
```

Nested eager loading:

```php
$users = User::query()
    ->with('posts.comments')
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

## Declared Properties

Myxa models are strict. Persisted fields should be declared as real PHP properties on the model class.

Good examples:

```php
protected string $email = '';
protected ?string $name = null;
protected ?int $user_id = null;
```

Practical rules:

- if a field belongs to the model, declare it
- if a field may be missing, make it nullable or give it a sensible default
- if you use typed properties without defaults, initialize them before relying on them
- metadata properties like `$table`, `$primaryKey`, and `$connection` are separate from normal persisted attributes

Normal writes are strict:

- `fill([...])` accepts only declared, non-guarded properties
- `setAttribute()` accepts only declared model properties
- `$model->property = ...` follows the same rule
- unknown attributes throw an exception during normal writes

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

## Casting

Models support property-level casts through the `#[Cast(...)]` attribute.

Built-in cast types supported by the core framework today:

- `CastType::DateTime`
- `CastType::DateTimeImmutable`
- `CastType::Json`

```php
use DateTimeImmutable;
use Myxa\Database\Attributes\Cast;
use Myxa\Database\Model\CastType;

final class User extends Model
{
    protected string $table = 'users';

    protected ?int $id = null;
    protected string $email = '';

    #[Cast(CastType::DateTimeImmutable, format: DATE_ATOM)]
    protected ?DateTimeImmutable $created_at = null;

    #[Cast(CastType::Json)]
    protected ?array $settings = null;
}
```

Notes:

- hydrated datetime strings are cast into `DateTime` or `DateTimeImmutable`
- hydrated JSON strings are decoded when using `CastType::Json`
- `null` values are left as `null`
- serialized output converts datetime values back to strings
- SQL persistence stores JSON-cast attributes as JSON strings
- invalid values throw an `InvalidArgumentException`

## Extra Hydrated Columns

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

## Further Reading

- [Database](database.md)
- [Database Migrations](database-migrations.md)
- [Validation](validation.md)
- `vendor/200mph/myxa-framework/src/Database/Model/README.md`
- `vendor/200mph/myxa-framework/src/Database/Query/README.md`
