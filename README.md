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

The app now wires the framework cache manager to a file-backed store in `storage/data/cache`.

Route caching is intended for production deployments:

- `APP_ENV=production` enables route cache usage by default
- `composer route:cache` compiles `routes/*.php` into `storage/cache/framework/routes.php`
- `composer route:clear` removes the compiled manifest

Route cache compilation only supports cacheable route definitions. Closure handlers or closure-based middleware should be replaced with controller actions or class-based middleware before caching.

## Assets

Use the following convention for frontend files:

- `public/assets/` for browser-served assets
- `resources/` for source assets you edit before a build step exists
- `storage/` for runtime-generated files such as uploads
