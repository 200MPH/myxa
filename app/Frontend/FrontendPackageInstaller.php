<?php

declare(strict_types=1);

namespace App\Frontend;

use Closure;
use RuntimeException;

class FrontendPackageInstaller
{
    private readonly ?Closure $executor;

    /**
     * @param (callable(
     *     list<string>,
     *     string,
     *     (callable(string): void)|null
     * ): array{exitCode: int, stdout: string, stderr: string})|null $executor
     */
    public function __construct(
        private readonly string $basePath = '',
        ?callable $executor = null,
        private readonly string $nodeImage = 'node:22-alpine',
    ) {
        $this->executor = $executor !== null ? Closure::fromCallable($executor) : null;
    }

    /**
     * @return array{strategy: string, command: string}
     */
    public function install(?callable $output = null): array
    {
        $cwd = $this->workingDirectory();

        if ($this->commandExists('npm', $cwd)) {
            $result = $this->execute(['npm', 'install'], $cwd, $output);

            if ($result['exitCode'] !== 0) {
                throw new RuntimeException($this->failureMessage('npm install', $result));
            }

            return [
                'strategy' => 'native npm',
                'command' => 'npm install',
            ];
        }

        if ($this->commandExists('docker', $cwd)) {
            $command = [
                'docker',
                'run',
                '--rm',
                '--user',
                $this->currentUser(),
                '-v',
                $cwd . ':/app',
                '-w',
                '/app',
                $this->nodeImage,
                'npm',
                'install',
            ];
            $result = $this->execute($command, $cwd, $output);

            if ($result['exitCode'] !== 0) {
                throw new RuntimeException($this->failureMessage('Docker npm install', $result));
            }

            return [
                'strategy' => 'Docker',
                'command' => sprintf('docker run --rm %s npm install', $this->nodeImage),
            ];
        }

        throw new RuntimeException(
            'npm was not found, and Docker is not available for a temporary Node container.',
        );
    }

    private function commandExists(string $command, string $cwd): bool
    {
        $result = $this->execute([$command, '--version'], $cwd, null);

        return $result['exitCode'] === 0;
    }

    /**
     * @param list<string> $command
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function execute(array $command, string $cwd, ?callable $output): array
    {
        if ($this->executor !== null) {
            return ($this->executor)($command, $cwd, $output);
        }

        return $this->executeProcess($command, $cwd, $output);
    }

    /**
     * @param list<string> $command
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeProcess(array $command, string $cwd, ?callable $output): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            return [
                'exitCode' => 127,
                'stdout' => '',
                'stderr' => sprintf('Unable to start command [%s].', implode(' ', $command)),
            ];
        }

        foreach ([1, 2] as $index) {
            if (is_resource($pipes[$index] ?? null)) {
                stream_set_blocking($pipes[$index], false);
            }
        }

        $stdout = '';
        $stderr = '';

        $exitCode = null;

        while (true) {
            $status = proc_get_status($process);
            $stdout .= $this->readPipe($pipes[1] ?? null, $output);
            $stderr .= $this->readPipe($pipes[2] ?? null, $output);

            if (!$status['running']) {
                $statusExitCode = (int) $status['exitcode'];
                $exitCode = $statusExitCode >= 0 ? $statusExitCode : null;

                break;
            }

            usleep(100000);
        }

        $stdout .= $this->readPipe($pipes[1] ?? null, $output);
        $stderr .= $this->readPipe($pipes[2] ?? null, $output);

        foreach ([1, 2] as $index) {
            if (is_resource($pipes[$index] ?? null)) {
                fclose($pipes[$index]);
            }
        }

        $closeExitCode = proc_close($process);
        $exitCode ??= $closeExitCode;

        return [
            'exitCode' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }

    private function readPipe(mixed $pipe, ?callable $output): string
    {
        if (!is_resource($pipe)) {
            return '';
        }

        $chunk = stream_get_contents($pipe);

        if (!is_string($chunk) || $chunk === '') {
            return '';
        }

        if ($output !== null) {
            foreach (preg_split('/\R/', trim($chunk)) ?: [] as $line) {
                if ($line !== '') {
                    $output($line);
                }
            }
        }

        return $chunk;
    }

    /**
     * @param array{exitCode: int, stdout: string, stderr: string} $result
     */
    private function failureMessage(string $label, array $result): string
    {
        $details = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);

        return sprintf(
            '%s failed with exit code %d%s',
            $label,
            $result['exitCode'],
            $details !== '' ? sprintf(': %s', $details) : '.',
        );
    }

    private function workingDirectory(): string
    {
        return $this->basePath !== '' ? rtrim($this->basePath, DIRECTORY_SEPARATOR) : base_path();
    }

    private function currentUser(): string
    {
        $uid = function_exists('posix_getuid') ? (string) posix_getuid() : '1000';
        $gid = function_exists('posix_getgid') ? (string) posix_getgid() : '1000';

        return $uid . ':' . $gid;
    }
}
