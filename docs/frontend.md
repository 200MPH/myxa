# Frontend

This project is backend-first, but it can scaffold a hybrid Vue frontend when you want richer client-side interactions inside server-rendered pages.

## Install Vue Hybrid Mode

Run:

```bash
./myxa frontend:install vue
```

Then install the Node dependencies and build the bundle:

```bash
npm install
npm run frontend:build
```

For ongoing local work, you can also use:

```bash
npm run frontend:watch
```

The install command scaffolds:

- `package.json` frontend scripts and Vue/Vite dev dependencies
- `vite.config.mjs`
- `resources/frontend/app.js`
- `resources/frontend/components/CounterWidget.vue`
- a safe bundle include in `resources/views/layouts/app.php`

The bundle is only loaded when `public/assets/frontend/app.js` exists, so the backend-first default flow remains intact until you actually build the frontend assets.

## Hybrid Mount Pattern

The generated Vue entrypoint scans for elements that declare a component name:

```php
<div
    data-vue-component="CounterWidget"
    data-vue-props='<?= $_e(json_encode([
        'title' => 'Orders dashboard',
        'initialCount' => 3,
    ], JSON_THROW_ON_ERROR)) ?>'
></div>
```

That keeps routing, layout, auth, and most rendering in PHP while letting Vue enhance specific parts of the page.

## Recommended Approach

For this project, hybrid mode is the best fit:

- server-rendered views stay fast and simple
- Vue can own interactive widgets or complex admin screens
- you avoid turning the main app into a full SPA

If a product eventually becomes SPA-heavy, it is usually cleaner to move that frontend into a separate repo or a dedicated API-first app.
