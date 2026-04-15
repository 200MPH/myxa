# Myxa is a tiny, flexible, powerful, and quietly smart PHP Framework

Ultra-light, modern PHP framework built for speed, clarity, and extensibility.
Inspired by nature. Built for developers.

## Local development with Docker

This project ships with a local stack built around:

- PHP 8.4 FPM
- nginx
- MySQL 8.4
- Redis 7
- local HTTPS support

### Start the stack

1. Copy `.env.example` to `.env`.
2. Run `docker compose up --build -d`.
3. Open `https://myxa.localhost`.

### Useful commands

- Install PHP dependencies: `docker compose exec app composer install`
- Open a shell in the PHP container: `docker compose exec app sh`
- Show available app commands: `./myxa`
- Show the current app version: `./myxa version:show`
- Generate `version.json` from Git metadata: `./myxa version:sync`
- Build the route cache: `docker compose exec app composer route:cache`
- Clear the route cache: `docker compose exec app composer route:clear`
- Stop the stack: `docker compose down`

Database is exposed on `localhost:3306` and Redis on `localhost:6380` by default.

If port `80` is already in use on your machine, change `APP_PORT` in `.env` and use that port in the browser instead.
If port `443` is already in use on your machine, change `APP_SSL_PORT` in `.env` and use that HTTPS port instead.

On first boot, nginx generates a self-signed certificate for `myxa.localhost` automatically.
Your browser will likely show a warning until you trust the certificate authority or switch to a tool such as `mkcert`.
Docker Desktop will show the stack as `myxa-project` by default. Change `COMPOSE_PROJECT_NAME` in `.env` if you want a different name.

PHP settings are split by environment:

- `APP_ENV=local` uses development PHP settings with visible errors
- `APP_ENV=production` uses production PHP settings with hidden errors and logging enabled

## Caching

The app now wires the framework cache manager to a file-backed store in `storage/cache`.

Route caching is intended for production deployments:

- `APP_ENV=production` enables route cache usage by default
- `./myxa` shows every command registered by `App\Console\Kernel`
- `composer route:cache` compiles `routes/*.php` into `storage/cache/framework/routes.php`
- `composer route:clear` removes the compiled manifest

Route cache compilation only supports cacheable route definitions. Closure handlers or closure-based middleware should be replaced with controller actions or class-based middleware before caching.

## Versioning

Application version metadata is stored in a generated `version.json` manifest at the project root.

- `./myxa version:sync` reads Git metadata and writes the manifest
- `./myxa version:show` prints the currently resolved version details
- `./myxa --version` uses the same resolved application version
- `/health` includes the resolved version and version source

Recommended workflow:

1. Create and push your Git tag as usual.
2. During CI or deployment, run `./myxa version:sync`.
3. Ship the generated `version.json` with the built artifact or deploy output.

`version.json` is intentionally ignored by Git so runtime code can read a stable manifest without requiring `.git` to be present in production.

## Assets

Use the following convention for frontend files:

- `public/assets/` for browser-served assets
- `resources/` for source assets you edit before a build step exists
- `storage/` for runtime-generated files such as uploads
