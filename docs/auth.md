# Auth

Myxa ships with a small but practical auth layer built around two guards:

- `web`: cookie + session based auth for browser routes
- `api`: bearer-token auth for API routes

The app skeleton already wires the auth services through `App\Providers\AuthServiceProvider`, so you can use sessions, tokens, auth middleware, and auth-related CLI commands without extra framework setup.

## What The App Supports

- user records in the `users` table
- persistent web sessions
- personal access tokens for APIs and automation
- file, Redis, or database-backed session storage
- guard-aware route protection with `web` and `api`

## First Setup

Generate the auth migrations:

```bash
./myxa auth:install
./myxa migrate
```

That creates the storage used by:

- `users`
- `personal_access_tokens`
- `user_sessions` unless you pass `--without-sessions`

If you only want file- or Redis-backed sessions and do not want a database session table, you can use:

```bash
./myxa auth:install --without-sessions
./myxa migrate
```

## Main Config

The auth settings live in:

```text
config/auth.php
```

Important choices there:

- session driver: `file`, `redis`, or `database`
- session cookie name and lifetime
- Redis connection and prefix for session storage
- default token name, length, and scopes

Common environment variables:

```text
AUTH_SESSION_DRIVER=file
AUTH_SESSION_COOKIE=myxa_session
AUTH_SESSION_LIFETIME=1209600
AUTH_SESSION_SECURE=false
AUTH_SESSION_REDIS_CONNECTION=default
AUTH_SESSION_REDIS_PREFIX=session:
AUTH_TOKEN_LENGTH=40
AUTH_TOKEN_NAME=cli
AUTH_TOKEN_SCOPES=*
```

## Protecting Routes

Use the built-in auth middleware from the framework:

```php
use Myxa\Middleware\AuthMiddleware;
use Myxa\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(AuthMiddleware::using('web'));

Route::get('/api/me', [ProfileController::class, 'show'])
    ->middleware(AuthMiddleware::using('api'));
```

Behavior:

- `web` redirects unauthenticated browser requests
- `api` returns an unauthenticated response and adds a `WWW-Authenticate: Bearer` header

## Getting The Current User

Inject `AuthManager` into a controller and resolve the user for the current guard:

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

If the current request was authenticated by a bearer token, the default `User` model also exposes:

- `currentAccessToken()`
- `hasTokenScope('users:read')`

## Session-Based Login Flow

The skeleton gives you the pieces, but it does not force a full login controller on you. A typical manual login flow is:

1. Look up the user with `UserManager`
2. Verify the password
3. Issue a session with `SessionManager`
4. Write the session cookie to the response

Example:

```php
use App\Auth\AuthConfig;
use App\Auth\SessionManager;
use App\Auth\UserManager;
use Myxa\Http\Request;
use Myxa\Http\Response;

final class LoginController
{
    public function store(
        Request $request,
        Response $response,
        UserManager $users,
        SessionManager $sessions,
        AuthConfig $auth,
    ): Response {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $user = $users->find($email);

        if ($user === null || !$users->verifyPassword($user, $password)) {
            return $response->json(['error' => 'Invalid credentials.'], 422);
        }

        $issued = $sessions->issue($user);

        return $response
            ->redirect('/dashboard')
            ->cookie(
                $auth->sessionCookieName(),
                $issued['plain_text_session'],
                time() + $auth->sessionLifetime(),
                '/',
                '',
                $auth->sessionSecure(),
                $auth->sessionHttpOnly(),
                $auth->sessionSameSite(),
            );
    }
}
```

For logout, revoke the current session in `SessionManager` and expire the cookie on the response.

## Personal Access Tokens

The `api` guard uses bearer tokens backed by the `personal_access_tokens` table.

Create a token from the CLI:

```bash
./myxa token:create admin@example.com --name=deploy --scopes=users:read,users:write --expires="+30 days"
```

That command prints the plain token once. Store it immediately, because only the hash is persisted.

Runtime example:

```php
use App\Auth\TokenManager;

$issued = $tokens->issue(
    user: 'admin@example.com',
    name: 'deploy',
    scopes: ['users:read', 'users:write'],
);

$plainTextToken = $issued['plain_text_token'];
```

Then use it as a bearer token:

```text
Authorization: Bearer <plain-text-token>
```

## Useful CLI Commands

- `auth:install`: create the auth migrations
- `user:create`: create a user
- `user:list`: list users
- `user:password`: rotate a password
- `token:create`: issue a bearer token
- `token:list`: inspect tokens
- `token:revoke`: revoke a token
- `token:prune`: remove revoked or expired tokens

Examples:

```bash
./myxa user:create admin@example.com --name="Admin User"
./myxa user:password admin@example.com
./myxa token:list admin@example.com
./myxa token:revoke 12
./myxa token:prune
```

## Session Storage Choices

You can keep the auth behavior the same while changing where sessions live:

- `file`: simple local development and single-node apps
- `redis`: better for scaled multi-node apps
- `database`: useful when you want session persistence in SQL

Recommendation:

- use `file` locally
- use `redis` for shared production web nodes
- use `database` only when SQL-backed sessions fit your infrastructure better than Redis

## Related Guides

- [HTTP, Routing, Controllers, and Middleware](http-routing.md)
- [Configuration](configuration.md)
- [Console and Scaffolding](console-and-scaffolding.md)
