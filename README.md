# Myxa Project

This repository is the application skeleton built on top of the Myxa framework. Myxa is a lightweight PHP framework powered by AI, and this project ships with Docker, a growing CLI, app-level providers, and examples of how to build HTTP, console, database, auth, cache, storage, queue, event, and rate-limit features in one place.

The developer experience is intentionally close to Laravel style, so teams coming from Laravel should feel at home quickly. The biggest differences are that the model and validator layers are leaner and a bit more explicit.

## Quick Start

1. Copy the environment file:

```bash
cp .env.example .env
```

2. Start the local stack:

```bash
docker compose up --build -d
```

3. Install PHP dependencies inside the app container:

```bash
docker compose exec app composer install
```

4. Open the app:

```text
https://myxa.localhost
```

If you want the full command list, run:

```bash
./myxa
```

## Install Via Composer

If you prefer starting from Composer instead of cloning the repository first, create a new project like this:

```bash
composer create-project 200mph/myxa my-app dev-develop
cd my-app
cp .env.example .env
docker compose up --build -d
```

Then open:

```text
https://myxa.localhost
```

This Composer-based path assumes your host already has Composer and a compatible PHP version available. The fuller setup notes are in [Getting Started](docs/getting-started.md).

## Common Commands

```bash
./myxa version:show
./myxa route:cache
./myxa route:clear
./myxa cache:clear
./myxa queue:status
./myxa queue:work --once
./myxa queue:retry <id>
./myxa queue:retry-all
./myxa queue:prune-failed --older-than=7d
./myxa storage:link
./myxa frontend:install vue
./myxa migrate
```

## Documentation

- [Getting Started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Console and Scaffolding](docs/console-and-scaffolding.md)
- [HTTP, Routing, Controllers, and Middleware](docs/http-routing.md)
- [Database, Query Builder, Models, and Migrations](docs/database.md)
- [Frontend](docs/frontend.md)
- [Queues](docs/queues.md)
- [Events, Listeners, and Services](docs/events-and-services.md)
- [Cache and Storage](docs/cache-and-storage.md)

## Project Notes

- The preferred local CLI entry point is `./myxa`.
- Routes are not cached automatically. Use `./myxa route:cache` when you want a compiled route manifest.
- Public file URLs should use `/storage/...`, not `/public/storage/...`.
- The `app` Docker service runs as your host UID/GID by default so files created by web requests are easier to manage on the host.

For deeper framework internals, the upstream module guides live under `vendor/200mph/myxa-framework/src/*/README.md`.
