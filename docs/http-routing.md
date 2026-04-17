# HTTP, Routing, Controllers, and Middleware

The app HTTP entry point lives in `public/index.php`, which boots `bootstrap/http.php` and then runs `App\Http\Kernel`.

Routes are loaded from:

```text
routes/*.php
```

Today the project ships with `routes/web.php`.

## Basic Routes

Example route file:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Myxa\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/health', [HealthController::class, 'show']);
```

Supported helpers include:

- `Route::get()`
- `Route::post()`
- `Route::put()`
- `Route::patch()`
- `Route::delete()`
- `Route::options()`
- `Route::head()`
- `Route::match()`
- `Route::any()`

## Route Parameters

```php
Route::get('/users/{id}', [UserController::class, 'show']);
```

The route parameter is injected into the handler:

```php
final class UserController
{
    public function show(string $id): string
    {
        return "User {$id}";
    }
}
```

## Route Groups

Prefix group:

```php
Route::group('/api', static function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
```

Middleware-only group:

```php
Route::middleware([
    \App\Http\Middleware\ThrottleMiddleware::using('api'),
], static function (): void {
    Route::get('/reports', [ReportController::class, 'index']);
});
```

## Controllers

Generate a controller:

```bash
./myxa make:controller User
./myxa make:controller Admin/User --resource
```

Generated controllers live under:

```text
app/Http/Controllers
```

Example:

```php
use Myxa\Http\Request;
use Myxa\Http\Response;

final class UserController
{
    public function index(Request $request): Response
    {
        return (new Response())->json(['message' => 'ok']);
    }
}
```

## What a Controller Method Can Return

The kernel normalizes action return values like this:

- `Response` -> returned as-is
- `string` -> HTML/text response
- `array` or `object` -> JSON response
- `null` -> `204 No Content`

That means this is valid:

```php
public function health(): array
{
    return ['ok' => true];
}
```

And this is also valid:

```php
public function show(): Response
{
    return (new Response())->html('<h1>Hello</h1>');
}
```

Be careful with `void` methods:

- a controller action that returns `null` will produce a `204`

## Middleware

Generate middleware:

```bash
./myxa make:middleware Api/EnsureTenant
```

Generated middleware lives under:

```text
app/Http/Middleware
```

Example usage by class:

```php
use App\Http\Middleware\EnsureTenantMiddleware;

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(EnsureTenantMiddleware::class);
```

Generated middleware implements:

```php
public function handle(Request $request, Closure $next, RouteDefinition $route): mixed
```

## Auth Middleware

The framework ships guard-based auth middleware.

Protect a web route:

```php
use Myxa\Middleware\AuthMiddleware;

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(AuthMiddleware::using('web'));
```

Protect an API route:

```php
Route::get('/api/me', [ProfileController::class, 'show'])
    ->middleware(AuthMiddleware::using('api'));
```

Get the current user in a controller:

```php
use Myxa\Auth\AuthManager;
use Myxa\Http\Request;

final class ProfileController
{
    public function show(Request $request, AuthManager $auth): array
    {
        $user = $auth->user($request, 'api');

        return [
            'user' => $user?->toArray(),
        ];
    }
}
```

## Rate Limiting

The app includes preset-aware throttle middleware.

Use a preset:

```php
use App\Http\Middleware\ThrottleMiddleware;

Route::middleware([ThrottleMiddleware::using('api')], static function (): void {
    Route::get('/api/reports', [ReportController::class, 'index']);
});
```

Current presets live in:

```text
config/rate_limit.php
```

Examples:

- `api`
- `login`
- `uploads`

If you need one-off limits, you can use the framework middleware directly:

```php
use Myxa\Middleware\RateLimitMiddleware;

Route::post('/imports', [ImportController::class, 'store'])
    ->middleware(RateLimitMiddleware::using(10, 60, 'imports'));
```

## Response Helpers

You can return a response object directly:

```php
return (new Response())->json(['saved' => true], 201);
```

For HTML pages, the app uses `Html` rendering through `AppServiceProvider`:

```php
use Myxa\Http\Response;
use Myxa\Support\Html\Html;

public function show(Html $html): Response
{
    return (new Response())->html($html->renderPage(
        'pages/home',
        ['user' => $user],
        'layouts/app',
        ['title' => 'Dashboard'],
    ));
}
```

## Route Cache

Routes are not cached automatically.

Build the route cache:

```bash
./myxa route:cache
```

Clear it:

```bash
./myxa route:clear
```

Use cacheable handlers and middleware if you plan to compile routes.

## Notes

- Avoid duplicate method/path registrations. The current router keeps the first matching route.
- Keep browser routes and API routes clearly separated when possible.
- Use explicit controller method names such as `index`, `show`, and `store` unless the class is truly single-action.

## Further Reading

- `vendor/200mph/myxa-framework/src/Routing/README.md`
- `vendor/200mph/myxa-framework/src/Middleware/README.md`
- [Console and Scaffolding](console-and-scaffolding.md)
