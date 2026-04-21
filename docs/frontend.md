# Frontend

Myxa is backend-first. The optional frontend setup adds Vue and Vite for hybrid widgets inside server-rendered pages.

## Install

Install the Vue frontend scaffold:

```bash
./myxa frontend:install vue --npm
```

If you prefer to run npm yourself:

```bash
./myxa frontend:install vue
npm install
```

When using an existing Docker app container from before Node/npm was added to the image, rebuild it once:

```bash
docker compose up --build -d
```

## Build

Build the browser bundle:

```bash
npm run frontend:build
```

During local work, keep Vite watching:

```bash
npm run frontend:watch
```

Inside Docker:

```bash
docker compose exec -it app npm run frontend:watch
```

The app layout loads Vue only when this file exists:

```text
public/assets/frontend/app.js
```

## Test Page

Open:

```text
https://myxa.localhost/vue-test
```

This diagnostic page tells you whether the frontend is not installed, not built, or mounted correctly.

## Mount Vue

Add a mount element to any server-rendered view:

```php
<div
    data-vue-component="CounterWidget"
    data-vue-props='<?= $_e(json_encode([
        'title' => 'Orders dashboard',
        'initialCount' => 3,
    ], JSON_THROW_ON_ERROR)) ?>'
></div>
```

The generated entrypoint in `resources/frontend/app.js` scans for `data-vue-component` and mounts registered Vue components.

## Generated Files

`frontend:install` manages:

- `package.json`
- `vite.config.mjs`
- `resources/frontend/app.js`
- `resources/frontend/components/CounterWidget.vue`
- `public/assets/frontend/.gitignore`
- the frontend bundle include in `resources/views/layouts/app.php`
