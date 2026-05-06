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

## Maintenance and Versioning

- `maintenance:on`: put the app into maintenance mode.
- `maintenance:off`: bring the app back out of maintenance mode.
- `maintenance:status`: show whether maintenance mode is currently enabled.
- `version:sync`: refresh the stored application version metadata.
- `version:show`: print the current application version information.

Examples:

```bash
./myxa maintenance:on
./myxa maintenance:on --wait
./myxa maintenance:off
./myxa version:sync
```

## Cache, Routes, and Storage

- `cache:clear`: clear one cache store or the default cache store.
- `cache:forget`: remove one cache key from a store.
- `route:cache`: compile and write the route cache manifest.
- `route:clear`: remove the compiled route cache manifest.
- `storage:link`: create the public storage symlink for browser-accessible files.

Examples:

```bash
./myxa cache:clear
./myxa cache:forget users:123
./myxa cache:clear --store=local
./myxa route:cache
./myxa storage:link
```

## Queues

- `queue:failed`: list failed jobs currently in the DLQ.
- `queue:flush-failed`: delete all failed jobs, optionally for one queue.
- `queue:forget-failed`: delete one failed job from the DLQ by id.
- `queue:prune-failed`: delete failed jobs older than a given age such as `7d`.
- `queue:retry`: move one failed job back onto a live queue.
- `queue:retry-all`: move many failed jobs back onto a live queue.
- `queue:status`: show queue counts and operational state.
- `queue:work`: run a worker that consumes queued jobs.

Examples:

```bash
./myxa queue:status
./myxa queue:work --once
./myxa queue:retry job-123
./myxa queue:retry-all
./myxa queue:prune-failed --older-than=7d
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

- `frontend:install`: scaffold the hybrid frontend toolchain.
- `make:command`: generate a new console command class.
- `make:controller`: generate a new HTTP controller.
- `make:event`: generate a new application event class.
- `make:listener`: generate a new event listener and optionally register it.
- `make:middleware`: generate a new HTTP middleware class.
- `make:migration`: generate a new migration file.
- `make:model`: generate a new model from scratch or from an existing source.
- `make:resource`: generate a DTO-style data/resource class.
- `make:seeder`: generate a new application seeder.

## Database and Schema

- `migrate`: run pending migrations.
- `migrate:rollback`: roll back one or more migration batches.
- `migrate:status`: show which migrations have or have not run.
- `migrate:snapshot`: write a schema snapshot JSON file.
- `migrate:diff`: compare the live schema against a stored snapshot.
- `migrate:reverse`: generate a migration from an existing live table.
- `db:seed`: run the default seeder or a selected seeder.

## Auth, Users, and Tokens

- `auth:install`: generate the auth-related migrations used by the app.
- `user:create`: create a new user.
- `user:list`: list users from the app database.
- `user:password`: reset or generate a user password.
- `token:create`: create a personal access token for a user.
- `token:list`: list tokens for one user or for the whole app.
- `token:revoke`: revoke one token by id.
- `token:prune`: remove expired tokens.

## Frontend

- `frontend:install`: scaffold the hybrid Vue frontend integration.

Example:

```bash
./myxa frontend:install vue
```

That scaffolds a hybrid Vue + Vite frontend layer without turning the app into a full SPA. After the command finishes, run:

```bash
npm install
npm run frontend:build
```

Options:

- `vue`: the supported hybrid frontend stack to scaffold right now
- `--force`: overwrite managed frontend scaffold files when they already exist
- `--npm`: run `npm install` after scaffolding, using native npm or a temporary Docker Node container

More examples:

```bash
./myxa frontend:install vue --force
./myxa frontend:install vue --npm
npm run frontend:watch
```

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

Options:

- `--command=reports:send`: set the CLI command name explicitly
- `--description="..."`: add the command description in the generated class

More examples:

```bash
./myxa make:command Admin/ReindexSearch --command=search:reindex --description="Rebuild the search index."
./myxa make:command Reports/ArchiveDaily
```

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

Options:

- `--invokable`: generate a single-action controller using `__invoke()`
- `--resource`: generate a CRUD-style controller with common action methods

More examples:

```bash
./myxa make:controller Docs/Page --invokable
./myxa make:controller Admin/Users --resource
```

## Creating Middleware

```bash
./myxa make:middleware Api/EnsureTenant
```

This creates a class under `app/Http/Middleware`.

Examples:

```bash
./myxa make:middleware Auth/RequireAdmin
./myxa make:middleware Api/EnsureTokenScope
```

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

Option:

- `--event=Auth/UserLoggedIn`: generate a typed listener and register it automatically

More examples:

```bash
./myxa make:listener Users/SendWelcomeEmail --event=UserRegistered
./myxa make:listener Audit/RecordLogin
```

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

Migration generator options:

- `--create=posts`: generate a create-table migration scaffold
- `--table=posts`: generate an alter-table migration scaffold
- `--class=CreatePostsTable`: set the migration class name explicitly
- `--connection=mysql`: target one configured database connection

Examples:

```bash
./myxa make:migration add_status_to_orders_table --table=orders
./myxa make:migration create_audit_logs_table --create=audit_logs --connection=mysql
```

Run migrations:

```bash
./myxa migrate
```

Useful options:

- `--connection=mysql`: run only one connection

Example:

```bash
./myxa migrate --connection=mysql
```

Show migration status:

```bash
./myxa migrate:status
```

Example:

```bash
./myxa migrate:status --connection=mysql
```

Rollback the latest batch:

```bash
./myxa migrate:rollback --step=1
```

Rollback options:

- `--step=1`: choose how many batches to roll back
- `--connection=mysql`: roll back only one connection

Example:

```bash
./myxa migrate:rollback --step=2 --connection=mysql
```

Reverse-engineer from an existing table:

```bash
./myxa migrate:reverse users
```

Reverse-engineering options:

- `--connection=mysql`: source connection alias
- `--class=CreateLegacyUsersTable`: explicit migration class name

Example:

```bash
./myxa migrate:reverse legacy_users --connection=mysql --class=CreateLegacyUsersTable
```

Create a model:

```bash
./myxa make:model App\\Models\\AuditLog
```

Create a model from a live table:

```bash
./myxa make:model App\\Models\\AuditLog --from-table=audit_logs
```

Model generator options:

- `--table=audit_logs`: set a table name for a blank scaffold
- `--from-table=audit_logs`: reverse-engineer from a live table
- `--from-migration=20260101000000_create_audit_logs_table.php`: generate from a migration file
- `--connection=mysql`: pick the source connection for reverse-engineering

More examples:

```bash
./myxa make:model App\\Models\\Invoice --table=invoices
./myxa make:model App\\Models\\LegacyOrder --from-table=legacy_orders --connection=mysql
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

Option:

- `--without-sessions`: skip generating the database-backed session migration

More examples:

```bash
./myxa auth:install
./myxa auth:install --without-sessions
```

Create a user:

```bash
./myxa user:create jane@example.com --name="Jane"
```

User command options:

- `--name="Jane"`: set the display name
- `--password="secret"`: provide a password instead of generating one

More examples:

```bash
./myxa user:create admin@example.com --name="Admin"
./myxa user:create ops@example.com --name="Ops" --password="temporary-secret"
```

Create a bearer token:

```bash
./myxa token:create jane@example.com --name=cli --scopes=users:read,users:write
```

Token command options:

- `--name=cli`: set a token label
- `--scopes=users:read,users:write`: assign scopes
- `--expires="+7 days"`: set an expiration datetime

More examples:

```bash
./myxa token:create jane@example.com --name=deploy
./myxa token:create api@example.com --name=integration --scopes=users:read --expires="+30 days"
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

- [HTTP Routing](http-routing.md)
- [Database](database.md)
- [Database Migrations](database-migrations.md)
- [Events, Listeners, and Services](events-and-services.md)
