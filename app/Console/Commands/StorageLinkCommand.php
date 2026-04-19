<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Config\ConfigRepository;
use App\Console\Exceptions\CommandFailedException;
use Myxa\Console\Command;

final class StorageLinkCommand extends Command
{
    private string $publicPath;

    public function __construct(
        private readonly ConfigRepository $config,
        ?string $publicPath = null,
    ) {
        $this->publicPath = $publicPath ?? public_path();
    }

    public function name(): string
    {
        return 'storage:link';
    }

    public function description(): string
    {
        return 'Create the public storage symlink for files on the public disk.';
    }

    protected function handle(): int
    {
        $target = $this->publicDiskRoot();
        $link = rtrim($this->publicPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage';

        if (is_link($link)) {
            $currentTarget = readlink($link);
            $resolvedCurrentTarget = is_string($currentTarget)
                ? $this->normalizePath($currentTarget, dirname($link))
                : null;

            if ($resolvedCurrentTarget === $this->normalizePath($target)) {
                $this->info(sprintf('Public storage link already exists at %s', $link))->icon();

                return 0;
            }

            throw new CommandFailedException(sprintf(
                'Storage link [%s] already exists but points somewhere else.',
                $link,
            ));
        }

        if (file_exists($link)) {
            throw new CommandFailedException(sprintf(
                'Storage link path [%s] already exists and is not a symlink.',
                $link,
            ));
        }

        if (!is_dir(dirname($link)) && !@mkdir(dirname($link), 0777, true) && !is_dir(dirname($link))) {
            throw new CommandFailedException(sprintf('Unable to create public directory [%s].', dirname($link)));
        }

        if (!@symlink($target, $link)) {
            throw new CommandFailedException(sprintf(
                'Unable to create storage symlink from [%s] to [%s].',
                $link,
                $target,
            ));
        }

        $this->success(sprintf('Public storage link created at %s', $link))->icon();

        return 0;
    }

    private function publicDiskRoot(): string
    {
        $root = $this->config->get('storage.disks.public.root', storage_path('app/public'));

        if (!is_string($root) || trim($root) === '') {
            return storage_path('app/public');
        }

        return $root;
    }

    private function normalizePath(string $path, ?string $relativeTo = null): string
    {
        if ($relativeTo !== null && !$this->isAbsolutePath($path)) {
            $path = rtrim($relativeTo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        }

        $resolved = realpath($path);

        if (is_string($resolved)) {
            return $resolved;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($normalized, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
