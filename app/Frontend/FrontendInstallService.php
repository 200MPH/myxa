<?php

declare(strict_types=1);

namespace App\Frontend;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class FrontendInstallService
{
    private const string LAYOUT_MARKER = 'MYXA_FRONTEND_BUNDLE';

    public function __construct(private readonly string $basePath = '')
    {
    }

    /**
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>, warnings: list<string>}
     */
    public function install(string $stack = 'vue', bool $force = false): array
    {
        $stack = strtolower(trim($stack));

        if ($stack !== 'vue') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported frontend stack [%s]. Only vue is currently supported.',
                $stack,
            ));
        }

        $result = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'warnings' => [],
        ];

        $this->record($result, $this->updateRootGitignore());
        $this->record($result, $this->updatePackageJson());
        $this->record($result, $this->writeFile('vite.config.mjs', $this->viteConfigTemplate(), $force));
        $this->record($result, $this->writeFile('resources/frontend/app.js', $this->frontendEntryTemplate(), $force));
        $this->record(
            $result,
            $this->writeFile(
                'resources/frontend/components/CounterWidget.vue',
                $this->counterWidgetTemplate(),
                $force,
            ),
        );
        $this->record(
            $result,
            $this->writeFile('public/assets/frontend/.gitignore', "*\n!.gitignore\n", true),
        );
        $this->record($result, $this->injectLayoutBundleScript());

        return $result;
    }

    /**
     * @param array{created: list<string>, updated: list<string>, skipped: list<string>, warnings: list<string>} $result
     * @param array{status: string, path?: string, warning?: string} $change
     */
    private function record(array &$result, array $change): void
    {
        if (isset($change['path']) && in_array($change['status'], ['created', 'updated', 'skipped'], true)) {
            $result[$change['status']][] = $change['path'];
        }

        if (isset($change['warning'])) {
            $result['warnings'][] = $change['warning'];
        }
    }

    /**
     * @return array{status: string, path: string}
     */
    private function updatePackageJson(): array
    {
        $path = $this->path('package.json');
        $exists = is_file($path);

        $package = [
            'private' => true,
            'scripts' => [],
            'devDependencies' => [],
        ];

        if ($exists) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (!is_array($decoded)) {
                throw new RuntimeException(sprintf('Unable to decode package.json at [%s].', $path));
            }

            $package = $decoded;
        }

        if (!array_key_exists('private', $package)) {
            $package['private'] = true;
        }

        $scripts = $package['scripts'] ?? [];
        if (!is_array($scripts)) {
            $scripts = [];
        }

        $scripts['frontend:build'] = 'vite build';
        $scripts['frontend:watch'] = 'vite build --watch';
        $package['scripts'] = $scripts;

        $devDependencies = $package['devDependencies'] ?? [];
        if (!is_array($devDependencies)) {
            $devDependencies = [];
        }

        $devDependencies['@vitejs/plugin-vue'] = '^5.2.1';
        $devDependencies['vite'] = '^5.4.19';
        $devDependencies['vue'] = '^3.5.13';
        $package['devDependencies'] = $devDependencies;

        try {
            $encoded = json_encode(
                $package,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode package.json.', previous: $exception);
        }

        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode package.json.');
        }

        $encoded .= "\n";

        if ($exists && (string) file_get_contents($path) === $encoded) {
            return ['status' => 'skipped', 'path' => 'package.json'];
        }

        $this->ensureDirectoryExists(dirname($path));

        if (@file_put_contents($path, $encoded) === false) {
            throw new RuntimeException(sprintf('Unable to write package.json at [%s].', $path));
        }

        return [
            'status' => $exists ? 'updated' : 'created',
            'path' => 'package.json',
        ];
    }

    /**
     * @return array{status: string, path: string}
     */
    private function updateRootGitignore(): array
    {
        $path = $this->path('.gitignore');
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        $lines = preg_split('/\R/', $current) ?: [];
        $required = [
            'node_modules',
            'public/assets/frontend/*',
            '!public/assets/frontend/.gitignore',
        ];

        $updated = false;

        foreach ($required as $entry) {
            if (!in_array($entry, $lines, true)) {
                $lines[] = $entry;
                $updated = true;
            }
        }

        if (!$updated) {
            return ['status' => 'skipped', 'path' => '.gitignore'];
        }

        $contents = rtrim(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== ''))) . "\n";
        $this->ensureDirectoryExists(dirname($path));

        if (@file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write .gitignore at [%s].', $path));
        }

        return ['status' => $current === '' ? 'created' : 'updated', 'path' => '.gitignore'];
    }

    /**
     * @return array{status: string, path?: string, warning?: string}
     */
    private function injectLayoutBundleScript(): array
    {
        $path = $this->path('resources/views/layouts/app.php');

        if (!is_file($path)) {
            return [
                'status' => 'skipped',
                'warning' => 'resources/views/layouts/app.php was not found. Add the frontend bundle script manually.',
            ];
        }

        $source = (string) file_get_contents($path);
        if (str_contains($source, self::LAYOUT_MARKER)) {
            return ['status' => 'skipped', 'path' => 'resources/views/layouts/app.php'];
        }

        $snippet = <<<PHP

<?php if (is_file(public_path('assets/frontend/app.js'))) : ?>
<?php \$frontendAssetVersion = (string) filemtime(public_path('assets/frontend/app.js')); ?>
<!-- MYXA_FRONTEND_BUNDLE -->
<script
    type="module"
    src="/assets/frontend/app.js?v=<?= \$_e(\$frontendAssetVersion) ?>"
></script>
<?php endif; ?>
PHP;

        if (!str_contains($source, '</body>')) {
            return [
                'status' => 'skipped',
                'path' => 'resources/views/layouts/app.php',
                'warning' => 'The app layout does not contain </body>. Add the frontend bundle script manually.',
            ];
        }

        $updated = str_replace('</body>', $snippet . "\n</body>", $source);

        if (@file_put_contents($path, $updated) === false) {
            throw new RuntimeException(sprintf('Unable to update layout at [%s].', $path));
        }

        return ['status' => 'updated', 'path' => 'resources/views/layouts/app.php'];
    }

    /**
     * @return array{status: string, path: string}
     */
    private function writeFile(string $relativePath, string $contents, bool $force): array
    {
        $path = $this->path($relativePath);
        $exists = is_file($path);

        if ($exists && !$force) {
            if ((string) file_get_contents($path) === $contents) {
                return ['status' => 'skipped', 'path' => $relativePath];
            }

            return ['status' => 'skipped', 'path' => $relativePath];
        }

        $this->ensureDirectoryExists(dirname($path));

        if (@file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write frontend scaffold file [%s].', $path));
        }

        return ['status' => $exists ? 'updated' : 'created', 'path' => $relativePath];
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $directory));
        }
    }

    private function path(string $relativePath): string
    {
        $basePath = $this->basePath !== '' ? rtrim($this->basePath, DIRECTORY_SEPARATOR) : base_path();

        return $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function viteConfigTemplate(): string
    {
        return <<<'JS'
import path from 'node:path';
import fs from 'node:fs';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

function appEnv() {
  const envPath = path.resolve(__dirname, '.env');

  if (!fs.existsSync(envPath)) {
    return process.env.APP_ENV ?? 'production';
  }

  const match = fs
    .readFileSync(envPath, 'utf8')
    .match(/^APP_ENV=(.*)$/m);

  return match?.[1]?.trim().replace(/^["']|["']$/g, '') || process.env.APP_ENV || 'production';
}

const nodeEnv = appEnv() === 'production' ? 'production' : 'development';

export default defineConfig({
  plugins: [vue()],
  publicDir: false,
  define: {
    'process.env.NODE_ENV': JSON.stringify(nodeEnv),
  },
  build: {
    outDir: 'public/assets/frontend',
    emptyOutDir: false,
    lib: {
      entry: path.resolve(__dirname, 'resources/frontend/app.js'),
      formats: ['es'],
      fileName: () => 'app.js',
    },
  },
});
JS
            . "\n";
    }

    private function frontendEntryTemplate(): string
    {
        return <<<'JS'
import { createApp } from 'vue';
import CounterWidget from './components/CounterWidget.vue';

const registry = {
  CounterWidget,
};

for (const element of document.querySelectorAll('[data-vue-component]')) {
  const componentName = element.dataset.vueComponent;
  const component = registry[componentName];

  if (!component) {
    continue;
  }

  let props = {};
  const rawProps = element.dataset.vueProps;

  if (rawProps) {
    try {
      props = JSON.parse(rawProps);
    } catch (error) {
      console.warn(`Unable to parse Vue props for ${componentName}.`, error);
    }
  }

  createApp(component, props).mount(element);
}
JS
            . "\n";
    }

    private function counterWidgetTemplate(): string
    {
        return <<<'VUE'
<script setup>
import { ref } from 'vue';

const props = defineProps({
  title: {
    type: String,
    default: 'Vue hybrid mode is ready',
  },
  initialCount: {
    type: Number,
    default: 0,
  },
});

const count = ref(props.initialCount);

const shellStyle = {
  padding: '1rem 1.1rem',
  borderRadius: '18px',
  border: '1px solid rgba(148, 163, 184, 0.18)',
  background: 'rgba(15, 23, 42, 0.72)',
  color: '#e2e8f0',
};

const buttonStyle = {
  marginTop: '0.9rem',
  border: '0',
  borderRadius: '999px',
  padding: '0.55rem 0.9rem',
  background: '#14b8a6',
  color: '#03111f',
  fontWeight: '700',
  cursor: 'pointer',
};
</script>

<template>
  <section :style="shellStyle">
    <strong>{{ title }}</strong>
    <p>Current count: {{ count }}</p>
    <button type="button" :style="buttonStyle" @click="count += 1">Increment</button>
  </section>
</template>
VUE
            . "\n";
    }
}
