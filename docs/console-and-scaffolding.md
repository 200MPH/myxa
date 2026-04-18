# Console and Scaffolding

The preferred project CLI entry point is:

```bash
./myxa
```

It boots the app console kernel and runs commands inside the container when Docker is available.

## Basic Usage

Show the command list:

```bash
./myxa
```

Show help for one command:

```bash
./myxa help make:migration
./myxa make:migration --help
```

Built-in CLI options:

- `--help`
- `--interactive`
- `--quiet`
- `--version`

## Command Categories

## Maintenance and Versioning

- `maintenance:on`
- `maintenance:off`
- `maintenance:status`
- `version:sync`
- `version:show`

Examples:

```bash
./myxa maintenance:on
./myxa maintenance:on --wait
./myxa maintenance:off
./myxa version:sync
```

## Cache, Routes, and Storage

- `cache:clear`
- `cache:forget`
- `queue:failed`
- `queue:flush-failed`
- `queue:forget-failed`
- `queue:prune-failed`
- `queue:retry`
- `queue:retry-all`
- `queue:status`
- `queue:work`
- `route:cache`
- `route:clear`
- `storage:link`

Examples:

```bash
./myxa cache:clear
./myxa cache:forget users:123
./myxa cache:clear --store=local
./myxa queue:status
./myxa queue:work --once
./myxa queue:retry job-123
./myxa queue:retry-all
./myxa queue:prune-failed --older-than=7d
./myxa route:cache
./myxa storage:link
```

Useful queue command notes:

- `queue:work --once` is handy for local debugging because it processes one job and exits
- `queue:work <queue> --sleep=1 --max-idle=5` is a good pattern for short-lived workers
- `queue:work <queue> --max-jobs=500` is useful when you want long-running workers to recycle periodically
- `queue:retry <id>` retries one DLQ job
- `queue:retry-all [queue]` retries many DLQ jobs
- `queue:forget-failed <id>` deletes one failed job
- `queue:flush-failed [queue]` deletes all failed jobs for that scope
- `queue:prune-failed [queue] --older-than=7d` deletes only old failed jobs

## Scaffolding

- `make:command`
- `make:controller`
- `make:event`
- `make:listener`
- `make:middleware`
- `make:migration`
- `make:model`
- `make:resource`

## Database and Schema

- `migrate`
- `migrate:rollback`
- `migrate:status`
- `migrate:snapshot`
- `migrate:diff`
- `migrate:reverse`

## Auth, Users, and Tokens

- `auth:install`
- `user:create`
- `user:list`
- `user:password`
- `token:create`
- `token:list`
- `token:revoke`
- `token:prune`

## Creating New Commands

Generate a command:

```bash
./myxa make:command Reports/SendDigest --command=reports:send --description="Send the daily digest report."
```

That creates a class under:

```text
app/Console/Commands/Reports/SendDigestCommand.php
```

Important note:

- commands are auto-discovered from `app/Console/Commands`
- you do not need to register them manually in `Kernel.php`

## Creating Controllers

Default controller:

```bash
./myxa make:controller User
```

Invokable controller:

```bash
./myxa make:controller HealthCheck --invokable
```

Resource-style controller:

```bash
./myxa make:controller Admin/User --resource
```

## Creating Middleware

```bash
./myxa make:middleware Api/EnsureTenant
```

This creates a class under `app/Http/Middleware`.

## Creating Events and Listeners

Generate an event:

```bash
./myxa make:event Auth/UserLoggedIn
```

Generate a listener and auto-register it:

```bash
./myxa make:listener Auth/TrackLogin --event=Auth/UserLoggedIn
```

Important note:

- when you pass `--event`, the listener is added to `EventServiceProvider::listeners()`
- without `--event`, the listener class is generated but not registered automatically

## Creating DTO-Style Resources

Generate a data class:

```bash
./myxa make:resource Users/Profile
```

This creates an `App\Data\Users\ProfileData` class under `app/Data/Users`.

## Migrations and Models

Create a forward migration:

```bash
./myxa make:migration create_posts_table --create=posts
```

Run migrations:

```bash
./myxa migrate
```

Show migration status:

```bash
./myxa migrate:status
```

Rollback the latest batch:

```bash
./myxa migrate:rollback --step=1
```

Reverse-engineer from an existing table:

```bash
./myxa migrate:reverse users
```

Create a model:

```bash
./myxa make:model App\\Models\\AuditLog
```

Create a model from a live table:

```bash
./myxa make:model App\\Models\\AuditLog --from-table=audit_logs
```

## Auth Bootstrap

Generate auth migrations:

```bash
./myxa auth:install
./myxa migrate
```

If your session driver is `file` or `redis`, you can skip the optional database session table:

```bash
./myxa auth:install --without-sessions
./myxa migrate
```

Create a user:

```bash
./myxa user:create jane@example.com --name="Jane"
```

Create a bearer token:

```bash
./myxa token:create jane@example.com --name=cli --scopes=users:read,users:write
```

## Maintenance Mode Notes

When maintenance mode is enabled:

- web requests are short-circuited
- API requests return `503`
- new CLI commands are blocked unless they are allowed

The allowlist lives in:

```text
config/maintenance.php
```

## Tips

- Use slash-style names like `Admin/User` for nested scaffolds.
- Run `./myxa <command> --help` when you forget the parameters.
- Prefer `./myxa` over raw container commands for app-specific work.

## Further Reading

- [HTTP, Routing, Controllers, and Middleware](http-routing.md)
- [Database, Query Builder, Models, and Migrations](database.md)
- [Events, Listeners, and Services](events-and-services.md)
