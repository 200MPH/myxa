<?php

declare(strict_types=1);

namespace App\Maintenance;

use RuntimeException;

final class MaintenanceMode
{
    private readonly string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? base_path(), DIRECTORY_SEPARATOR);
    }

    public function markerPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'maintenance.json';
    }

    public function isEnabled(): bool
    {
        return is_file($this->markerPath());
    }

    /**
     * @return array{
     *     enabled_at?: string,
     *     enabled_at_unix?: int,
     *     activated_by?: string,
     *     activated_by_pid?: int
     * }
     */
    public function payload(): array
    {
        $contents = @file_get_contents($this->markerPath());
        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        return [
            'enabled_at' => is_string($decoded['enabled_at'] ?? null) ? $decoded['enabled_at'] : null,
            'enabled_at_unix' => is_int($decoded['enabled_at_unix'] ?? null) ? $decoded['enabled_at_unix'] : null,
            'activated_by' => is_string($decoded['activated_by'] ?? null) ? $decoded['activated_by'] : null,
            'activated_by_pid' => is_int($decoded['activated_by_pid'] ?? null) ? $decoded['activated_by_pid'] : null,
        ];
    }

    public function enable(?string $activatedBy = null): void
    {
        $payload = [
            'enabled_at' => gmdate(DATE_ATOM),
            'enabled_at_unix' => time(),
            'activated_by' => $activatedBy ?? 'maintenance:on',
            'activated_by_pid' => getmypid(),
        ];

        $encoded = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode maintenance state.');
        }

        if (@file_put_contents($this->markerPath(), $encoded . PHP_EOL, \LOCK_EX) === false) {
            throw new RuntimeException(sprintf(
                'Unable to write maintenance marker [%s].',
                $this->markerPath(),
            ));
        }
    }

    public function disable(): bool
    {
        $path = $this->markerPath();

        return !is_file($path) || @unlink($path);
    }

    public function beginConsoleActivity(string $command): string
    {
        $token = bin2hex(random_bytes(16));

        $this->mutateConsoleActivityState(function (array $state) use ($token, $command): array {
            $state['commands'][$token] = [
                'command' => $command,
                'pid' => getmypid(),
                'started_at' => gmdate(DATE_ATOM),
                'started_at_unix' => time(),
            ];

            return $state;
        });

        return $token;
    }

    public function endConsoleActivity(?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        $this->mutateConsoleActivityState(static function (array $state) use ($token): array {
            unset($state['commands'][$token]);

            return $state;
        });
    }

    /**
     * @return array<string, array{
     *     command?: string,
     *     pid?: int,
     *     started_at?: string,
     *     started_at_unix?: int
     * }>
     */
    public function activeConsoleCommands(): array
    {
        return $this->readConsoleActivityState()['commands'];
    }

    public function activeConsoleCommandCount(): int
    {
        return count($this->activeConsoleCommands());
    }

    public function waitForIdleConsole(?int $timeoutSeconds = null, int $pollMilliseconds = 250): bool
    {
        $deadline = $timeoutSeconds !== null && $timeoutSeconds > 0
            ? microtime(true) + $timeoutSeconds
            : null;

        $sleepMicroseconds = max(100, $pollMilliseconds) * 1000;

        while ($this->activeConsoleCommandCount() > 0) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                return false;
            }

            usleep($sleepMicroseconds);
        }

        return true;
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>}
     */
    private function readConsoleActivityState(): array
    {
        $this->ensureConsoleActivityDirectoryExists();

        $handle = fopen($this->consoleActivityPath(), 'c+b');
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                'Unable to open maintenance console state [%s].',
                $this->consoleActivityPath(),
            ));
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                throw new RuntimeException(sprintf(
                    'Unable to lock maintenance console state [%s].',
                    $this->consoleActivityPath(),
                ));
            }

            $state = $this->pruneConsoleActivityState($this->decodeConsoleActivityState($handle));
            $this->writeConsoleActivityState($handle, $state);
            flock($handle, \LOCK_UN);

            return $state;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param callable(array{commands: array<string, array<string, mixed>>}): array{commands: array<string, array<string, mixed>>} $callback
     */
    private function mutateConsoleActivityState(callable $callback): void
    {
        $this->ensureConsoleActivityDirectoryExists();

        $handle = fopen($this->consoleActivityPath(), 'c+b');
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                'Unable to open maintenance console state [%s].',
                $this->consoleActivityPath(),
            ));
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                throw new RuntimeException(sprintf(
                    'Unable to lock maintenance console state [%s].',
                    $this->consoleActivityPath(),
                ));
            }

            $state = $this->pruneConsoleActivityState($this->decodeConsoleActivityState($handle));
            $state = $callback($state);
            $this->writeConsoleActivityState($handle, $state);
            flock($handle, \LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function ensureConsoleActivityDirectoryExists(): void
    {
        $directory = dirname($this->consoleActivityPath());
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Unable to create maintenance state directory [%s].',
                $directory,
            ));
        }
    }

    private function consoleActivityPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'maintenance'
            . DIRECTORY_SEPARATOR . 'console-activity.json';
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>}
     */
    private function decodeConsoleActivityState(mixed $handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);

        if (!is_string($contents) || trim($contents) === '') {
            return ['commands' => []];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded) || !is_array($decoded['commands'] ?? null)) {
            return ['commands' => []];
        }

        /** @var array<string, array<string, mixed>> $commands */
        $commands = $decoded['commands'];

        return ['commands' => $commands];
    }

    /**
     * @param array{commands: array<string, array<string, mixed>>} $state
     * @return array{commands: array<string, array<string, mixed>>}
     */
    private function pruneConsoleActivityState(array $state): array
    {
        $commands = [];

        foreach ($state['commands'] as $token => $command) {
            $pid = is_int($command['pid'] ?? null) ? $command['pid'] : null;
            if ($pid === null || !$this->isProcessRunning($pid)) {
                continue;
            }

            $commands[$token] = $command;
        }

        return ['commands' => $commands];
    }

    /**
     * @param array{commands: array<string, array<string, mixed>>} $state
     */
    private function writeConsoleActivityState(mixed $handle, array $state): void
    {
        $encoded = json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode maintenance console state.');
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if ($pid === getmypid()) {
            return true;
        }

        $procPath = DIRECTORY_SEPARATOR . 'proc' . DIRECTORY_SEPARATOR . $pid;
        if (DIRECTORY_SEPARATOR === '/' && is_dir($procPath)) {
            return true;
        }

        if (function_exists('posix_kill')) {
            $running = @posix_kill($pid, 0);
            if ($running) {
                return true;
            }

            if (function_exists('posix_get_last_error')) {
                return posix_get_last_error() === 1;
            }
        }

        return false;
    }
}
