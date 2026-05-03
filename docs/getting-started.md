# Getting Started

This guide shows the shortest path from a fresh Myxa app to the welcome page.

If you already know Laravel, the project structure should feel very familiar. Service providers, commands, routing, jobs, and most day-to-day patterns are intentionally close, while the main differences are the slimmer model and validator layers.

## Requirements

You can run the app in either of these ways:

- Docker + Docker Compose
- host-native PHP environment with the required services already available

If you use Docker, you do not need PHP, MySQL, or Redis installed locally.
If your host already has PHP, Composer, MySQL, and Redis available, Docker is optional.

## Two Ways To Start

You can begin in either of these ways:

1. Create a fresh app with Composer, then choose either the Docker or host-native runtime style below.
2. If you are starting from GitHub instead, create your own repository from the Myxa skeleton before you begin development.

Do not treat the upstream `https://github.com/200MPH/myxa` repository as your application repository unless you are contributing to Myxa itself.

## Install Via Composer

If you want to start from Composer instead of cloning first, create the project like this:

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

Notes:

- this path expects Composer on your host machine
- it also expects a host PHP version compatible with the project requirements
- `composer create-project` already installs dependencies, so you do not need a separate `composer install` step before first boot
- after creation, you can use the normal `./myxa` workflow in your own project repository

## First Boot With Docker

1. Copy the environment file:

```bash
cp .env.example .env
```

2. Build and start the containers:

```bash
docker compose up --build -d
```

3. Install Composer dependencies inside the app container:

```bash
docker compose exec app composer install
```

4. Open the app in your browser:

```text
https://myxa.localhost
```

The default stack starts:

- `app`: PHP 8.4 FPM
- `web`: nginx with local HTTPS
- `db`: MySQL 8.4
- `redis`: Redis 7

## First Boot Without Docker

If your host already has the required services available, you can run the app without Docker.

Typical requirements:

- PHP 8.4 with the extensions required by `composer.json`
- Composer
- a running MySQL-compatible database
- a running Redis instance when you want Redis-backed cache, queue, sessions, or rate limiting

Basic flow:

1. Copy the environment file:

```bash
cp .env.example .env
```

2. Install PHP dependencies:

```bash
composer install
```

3. Update `.env` for your local services.

Typical examples:

```text
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_PORT=3306
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

4. Run migrations when needed:

```bash
./myxa migrate
```

5. Start the app with your preferred PHP web server setup.

For example, with PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

If you run behind Apache, point the virtual host `DocumentRoot` at the project's `public/` directory, not the repository root. The app now ships with `public/.htaccess` for front-controller routing, so make sure `mod_rewrite` is enabled and the vhost allows overrides or includes equivalent rewrite rules directly.

Typical Apache requirements:

- `DocumentRoot /path/to/your-app/public`
- `AllowOverride All` for that directory, or the same rewrite rules in the vhost
- `a2enmod rewrite`

## What You Should See

After the app is booted and dependencies are installed, visiting the configured app URL should render the Myxa homepage.

The homepage does not require you to run migrations first. Database-backed features such as auth, tokens, and your own models will need migrations when you start using them.

## First Useful Commands

Show the command list:

```bash
./myxa
```

Show the current app version:

```bash
./myxa version:show
```

Create the public storage symlink:

```bash
./myxa storage:link
```

Run pending migrations:

```bash
./myxa migrate
```

Generate auth migrations:

```bash
./myxa auth:install
./myxa migrate
```

## Day-to-Day Development

The recommended loop is:

1. Edit files on the host.
2. Refresh the browser.
3. Use `./myxa` for project commands.
4. Run tests either inside Docker or directly on the host, depending on your setup.

With Docker, the project directory is mounted into the containers, so code changes are visible immediately.

## Useful Local URLs and Ports

- Docker app: `https://myxa.localhost`
- host-native app example: `http://localhost:8000`
- MySQL: `localhost:3306` by default
- Redis: `localhost:6379` by default

The default compose file publishes:

- HTTP on `80`
- HTTPS on `443`

If you need different host ports, change `APP_PORT` and `APP_SSL_PORT` in `.env`.
You can also change `DB_FORWARD_PORT` and `REDIS_FORWARD_PORT` there if those host ports are already in use.

## Certificates

On first boot, nginx creates a self-signed certificate for `myxa.localhost`.

That means:

- your browser may show a warning until you trust it
- the app still works for local development

## Optional Setup Steps

If you want version metadata:

```bash
./myxa version:sync
```

If you want route caching for production-like testing:

```bash
./myxa route:cache
```

## Troubleshooting

### The page does not load after `docker compose up`

Run:

```bash
docker compose ps
docker compose logs web
docker compose logs app
```

Also make sure you ran:

```bash
docker compose exec app composer install
```

### The page does not load on a host-native setup

Check:

- `composer install` completed successfully
- `.env` points at your real local database and Redis services
- your PHP web server is pointing at the `public/` directory
- the configured `APP_URL` matches how you are opening the app

### Ports `80` or `443` are already in use

Change these in `.env`:

```text
APP_PORT=8080
APP_SSL_PORT=8443
```

Then restart the stack.

### A public file URL does not work

Make sure the public storage link exists:

```bash
./myxa storage:link
```

Use URLs like:

```text
https://myxa.localhost/storage/reports/example.pdf
```

Not:

```text
https://myxa.localhost/public/storage/reports/example.pdf
```

### I changed routes and the old ones still appear

If route caching is enabled, clear it:

```bash
./myxa route:clear
```

## Further Reading

- [Configuration](configuration.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- [HTTP Routing](http-routing.md)
- [Auth](auth.md)
- [Validation](validation.md)
- [Rate Limiting and Throttling](rate-limiting.md)
