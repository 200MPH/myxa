# Getting Started

This guide shows the shortest path from a fresh clone to the Myxa welcome page.

## Requirements

- Docker
- Docker Compose

You do not need PHP, MySQL, or Redis installed locally if you use the provided Docker stack.

## First Boot

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

## What You Should See

After the containers are healthy and dependencies are installed, visiting `https://myxa.localhost` should render the Myxa homepage.

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
4. Run `docker compose exec app composer test` or `vendor/bin/phpunit` inside the container when you want verification.

Because the project directory is mounted into the containers, code changes are visible immediately.

## Useful Local URLs and Ports

- app: `https://myxa.localhost`
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
- [HTTP, Routing, Controllers, and Middleware](http-routing.md)
