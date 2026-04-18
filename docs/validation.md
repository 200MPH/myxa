# Validation

Validation is built into the framework and already registered by the app through `FrameworkServiceProvider`.

The main difference from Laravel is the style: Myxa uses a fluent validation API instead of string rule lists like `'required|email|max:255'`.

## What It Looks Like

Create a validator from the shared facade:

```php
use Myxa\Support\Facades\Validator;

$validator = Validator::make([
    'name' => 'Jane',
    'email' => 'jane@example.com',
]);

$validator->field('name')->required()->string()->min(2)->max(50);
$validator->field('email')->required()->string()->email()->max(255);
```

Then either inspect the result:

```php
if ($validator->fails()) {
    $errors = $validator->errors();
} else {
    $validated = $validator->validated();
}
```

Or throw on failure:

```php
$validated = $validator->validate();
```

## Recommended Controller Pattern

For typical request validation, keep it explicit and let `validate()` fail fast:

```php
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Support\Facades\Validator;

final class UserController
{
    public function store(Request $request, Response $response): Response
    {
        $validator = Validator::make($request->all());

        $validator->field('name')->required()->string()->min(2)->max(50);
        $validator->field('email')->required()->string()->email()->max(255);
        $validator->field('password')->required()->string()->min(12)->max(255);

        $validated = $validator->validate();

        return $response->json([
            'validated' => $validated,
        ], 201);
    }
}
```

The app exception flow already handles `ValidationException`, so you usually do not need a local `try/catch` around `validate()`.

That is a little more explicit than Laravel form requests, but it is also very direct and easy to trace.

## Common Fluent Rules

Presence and nullability:

- `required()`
- `nullable()`

Types and format:

- `string()`
- `integer()`
- `numeric()`
- `boolean()`
- `array()`
- `email()`

Size:

- `min($value)`
- `max($value)`

The `min()` and `max()` meaning depends on the value type:

- strings use length
- arrays use item count
- numeric values use the numeric value itself

## Nested Fields and Array Items

The validator now supports nested array validation through field segments.

In app code, that means:

- use dot paths for nested fields such as `user.email`
- use `*` wildcards for array items such as `user.roles.*`

Example:

```php
$validator = Validator::make([
    'user' => [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'roles' => ['admin', 'editor'],
    ],
]);

$validator->field('user.name')->required()->string()->min(2)->max(50);
$validator->field('user.email')->required()->string()->email()->max(255);
$validator->field('user.roles')->required()->array()->min(1);
$validator->field('user.roles.*')->required()->string()->min(3);

$validated = $validator->validate();
```

The validated output keeps the nested shape:

```php
[
    'user' => [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'roles' => ['admin', 'editor'],
    ],
]
```

When wildcard validation fails, the error keys point to the concrete array item:

```php
[
    'user.roles.1' => [
        'The user.roles.1 field must be a string.',
    ],
]
```

That makes nested request payloads much more practical to validate without flattening the input first.

## Exists Validation

`exists()` can validate against several sources:

- a SQL model class
- a Mongo model class
- an array of allowed values
- a custom callback

Examples:

```php
use App\Models\User;

$validator->field('user_id')->required()->integer()->exists(User::class);
$validator->field('email')->required()->string()->exists(User::class, 'email');
$validator->field('role')->required()->exists(['admin', 'editor']);
$validator->field('code')->exists(
    static fn (mixed $value): bool => in_array($value, ['A', 'B'], true),
);
```

## Custom Messages

Every rule can accept a custom message.

String example:

```php
$validator->field('name')->required('Name is mandatory.');
```

Callback example:

```php
$validator->field('email')->email(
    static fn (mixed $value, string $field): string => sprintf(
        '%s "%s" is invalid.',
        $field,
        (string) $value,
    ),
);
```

## Validated Output

The validated result only includes configured fields that are present in the input.

```php
$validator = Validator::make([
    'name' => 'Jane',
    'email' => 'jane@example.com',
    'ignored' => 'value',
]);

$validator->field('name')->required()->string();
$validator->field('email')->required()->string()->email();

$validated = $validator->validate();
```

Result:

```php
[
    'name' => 'Jane',
    'email' => 'jane@example.com',
]
```

Nested configured fields are rebuilt into nested validated output rather than returned as flat dot-key arrays.

## Error Format

`errors()` returns field-grouped messages:

```php
[
    'email' => [
        'The email field must be a valid email address.',
    ],
]
```

That shape is easy to return from JSON APIs or map into server-rendered forms.

## When To Use `fails()` vs `validate()`

Use `fails()` when:

- you want to keep full control of the response
- you want to render a form again with structured errors
- you do not want exceptions in the normal flow

Use `validate()` when:

- you want a short fail-fast path
- you want the app exception flow to handle `ValidationException`
- the controller action is simple and API-oriented

## Mental Model For Laravel Developers

If you come from Laravel, the closest translation is:

- Laravel rule strings -> Myxa fluent field chains
- Laravel nested rule keys like `user.email` or `tags.*` -> the same dot-path style in Myxa
- Laravel `validated()` -> Myxa `validated()` or `validate()`
- Laravel form requests -> explicit validator setup inside your action or service

So the validation layer is leaner, but not smaller in capability for everyday app work.

## Related Guides

- [HTTP, Routing, Controllers, and Middleware](http-routing.md)
- [Database, Query Builder, Models, and Migrations](database.md)
