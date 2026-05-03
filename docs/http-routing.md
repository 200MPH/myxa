# HTTP Routing

HTTP requests enter through `public/index.php`, boot `bootstrap/http.php`, and are handled by `App\Http\Kernel`.

Routes are loaded from:

```text
routes/*.php
```

The app skeleton ships with `routes/web.php`.

## Quick Example

Most endpoints are just a route, a controller method, a request, and a response:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Myxa\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Myxa\Http\Request;
use Myxa\Http\Response;

final class UserController
{
    public function index(Request $request): array
    {
        return [
            'page' => $request->query('page', 1),
            'users' => [],
        ];
    }

    public function store(Request $request, Response $response): Response
    {
        $name = (string) $request->input('name', '');
        $email = (string) $request->input('email', '');

        return $response->json([
            'name' => $name,
            'email' => $email,
        ], 201);
    }

    public function show(string $id): array
    {
        return ['id' => $id];
    }
}
```

## Defining Routes

Use the `Route` facade in route files:

```php
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HealthController;
use Myxa\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/health', [HealthController::class, 'show']);
```

Supported helpers:

```php
Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
Route::put('/posts/{id}', [PostController::class, 'replace']);
Route::patch('/posts/{id}', [PostController::class, 'update']);
Route::delete('/posts/{id}', [PostController::class, 'destroy']);
Route::options('/posts', [PostController::class, 'options']);
Route::head('/posts', [PostController::class, 'head']);

Route::match(['GET', 'POST'], '/search', [SearchController::class, 'handle']);
Route::any('/webhook', [WebhookController::class, 'handle']);
```

Typical use:

- `get()` reads a page or resource
- `post()` creates something or accepts a form
- `put()` replaces a resource
- `patch()` partially updates a resource
- `delete()` removes a resource
- `match()` accepts a small set of methods
- `any()` is useful for flexible endpoints such as webhooks

## Route Parameters

Named route parameters use `{name}` segments:

```php
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/posts/{postId}/comments/{commentId}', [CommentController::class, 'show']);
```

Parameters are injected into the handler by name:

```php
final class UserController
{
    public function show(string $id): array
    {
        return ['id' => $id];
    }
}
```

## Route Groups

Use groups when several routes share a prefix:

```php
Route::group('/api', static function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});
```

Use a middleware group when several routes share protection or request handling:

```php
use App\Http\Middleware\ThrottleMiddleware;

Route::middleware([ThrottleMiddleware::using('api')], static function (): void {
    Route::get('/api/reports', [ReportController::class, 'index']);
    Route::post('/api/imports', [ImportController::class, 'store']);
});
```

You can also combine a prefix and middleware:

```php
use Myxa\Middleware\AuthMiddleware;

Route::group('/admin', static function (): void {
    Route::get('/dashboard', [AdminDashboardController::class, 'show']);
    Route::get('/users', [AdminUserController::class, 'index']);
}, [AuthMiddleware::using('web')]);
```

For guard setup, see [Auth](auth.md). For throttle presets and stores, see [Rate Limiting and Throttling](rate-limiting.md).

## Controllers

Generate controllers with:

```bash
./myxa make:controller User
./myxa make:controller Admin/User --resource
```

Generated controllers live in:

```text
app/Http/Controllers
```

Controller methods can receive route parameters, `Request`, `Response`, and other container-resolved dependencies:

```php
use Myxa\Http\Request;
use Myxa\Http\Response;

final class ReportController
{
    public function show(string $id, Request $request, Response $response): Response
    {
        return $response->json([
            'id' => $id,
            'format' => $request->query('format', 'summary'),
        ]);
    }
}
```

## Requests

Inject `Myxa\Http\Request` when a controller needs request data:

```php
use Myxa\Http\Request;

final class SearchController
{
    public function index(Request $request): array
    {
        return [
            'query' => $request->query('q', ''),
            'page' => $request->query('page', 1),
            'tenant' => $request->header('X-Tenant', 'public'),
            'token' => $request->bearerToken(),
        ];
    }
}
```

Common request helpers:

- `$request->query('key', $default)` reads query string values
- `$request->post('key', $default)` reads POST body values
- `$request->input('key', $default)` reads merged query and POST input
- `$request->all()` returns merged query and POST input
- `$request->header('Name', $default)` reads headers
- `$request->cookie('name', $default)` reads cookies
- `$request->file('name', $default)` reads uploaded files
- `$request->method()`, `$request->path()`, `$request->fullUrl()`, and `$request->ip()` read request metadata

For small route closures, the request facade is also available:

```php
use Myxa\Support\Facades\Request;
use Myxa\Support\Facades\Route;

Route::get('/request-preview', static function (): array {
    return [
        'method' => Request::method(),
        'path' => Request::path(),
        'input' => Request::all(),
    ];
});
```

## Responses

Controller methods may return:

- `Response` returned as-is
- `string` as an HTML/text response
- `array` or `object` as a JSON response
- `null` as `204 No Content`

Simple JSON responses can return arrays directly:

```php
public function health(): array
{
    return ['ok' => true];
}
```

Use `Myxa\Http\Response` when you need a status code, headers, cookies, redirects, or explicit content type:

```php
use Myxa\Http\Response;

final class LoginController
{
    public function store(Response $response): Response
    {
        return $response
            ->cookie('notice', 'signed-in', path: '/')
            ->redirect('/dashboard');
    }
}
```

Useful response helpers:

```php
$response->json(['created' => true], 201);
$response->html('<h1>Hello</h1>');
$response->text('Accepted', 202);
$response->redirect('/login');
$response->noContent();
$response->setHeader('X-Trace-Id', 'req-123');
$response->cookie('theme', 'dark');
```

The response facade is handy in short route handlers:

```php
use Myxa\Support\Facades\Response;
use Myxa\Support\Facades\Route;

Route::post('/imports', static function () {
    return Response::json(['queued' => true], 202)
        ->setHeader('X-Import-Queued', 'yes');
});
```

## Middleware

Middleware runs around a route handler. Use it for concerns such as authentication, throttling, tenant detection, headers, or request logging.

Generate middleware with:

```bash
./myxa make:middleware Api/EnsureTenant
```

Generated middleware lives in:

```text
app/Http/Middleware
```

Generated middleware implements this method:

```php
public function handle(Request $request, Closure $next, RouteDefinition $route): mixed
```

A simple middleware can block a request before it reaches the controller:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Middleware\MiddlewareInterface;
use Myxa\Routing\RouteDefinition;

final class EnsureTenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, RouteDefinition $route): mixed
    {
        if ($request->header('X-Tenant') === null) {
            return (new Response())->json(['error' => 'Tenant header is required.'], 400);
        }

        return $next($request);
    }
}
```

Attach middleware to one route:

```php
use App\Http\Middleware\EnsureTenantMiddleware;

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(EnsureTenantMiddleware::class);
```

Attach middleware to a group:

```php
Route::middleware([EnsureTenantMiddleware::class], static function (): void {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/imports', [ImportController::class, 'store']);
});
```

Built-in auth and throttling middleware are used the same way:

```php
use App\Http\Middleware\ThrottleMiddleware;
use Myxa\Middleware\AuthMiddleware;

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(AuthMiddleware::using('web'));

Route::post('/api/imports', [ImportController::class, 'store'])
    ->middleware(ThrottleMiddleware::using('uploads'));
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

- Keep browser routes and API routes clearly separated when possible.
- Avoid duplicate method and path registrations. The router keeps the first matching route.
- Prefer explicit controller method names such as `index`, `show`, `store`, `update`, and `destroy`.
- Put detailed auth and throttle behavior in their dedicated docs, and keep this guide focused on request-to-response flow.

## Further Reading

- [Auth](auth.md)
- [Rate Limiting and Throttling](rate-limiting.md)
- [Validation](validation.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- `vendor/200mph/myxa-framework/src/Routing/README.md`
- `vendor/200mph/myxa-framework/src/Http/README.md`
- `vendor/200mph/myxa-framework/src/Middleware/README.md`
