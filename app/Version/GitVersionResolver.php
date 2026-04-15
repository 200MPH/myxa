<?php

declare(strict_types=1);

namespace App\Version;

use RuntimeException;

class GitVersionResolver
{
    /**
     * @return array{
     *     version: string,
     *     describe: string,
     *     tag: string|null,
     *     commit: string,
     *     short_commit: string,
     *     dirty: bool
     * }
     */
    public function resolve(string $basePath): array
    {
        $describe = $this->run(['git', '-C', $basePath, 'describe', '--tags', '--always', '--dirty']);
        $tag = $this->optionalFirstLine(['git', '-C', $basePath, 'tag', '--points-at', 'HEAD']);
        $commit = $this->run(['git', '-C', $basePath, 'rev-parse', 'HEAD']);
        $shortCommit = $this->run(['git', '-C', $basePath, 'rev-parse', '--short', 'HEAD']);

        return [
            'version' => $tag ?? $describe,
            'describe' => $describe,
            'tag' => $tag,
            'commit' => $commit,
            'short_commit' => $shortCommit,
            'dirty' => str_contains($describe, '-dirty'),
        ];
    }

    /**
     * @param list<string> $command
     */
    protected function run(array $command): string
    {
        [$exitCode, $stdout, $stderr] = $this->execute($command);

        if ($exitCode !== 0 || $stdout === '') {
            throw new RuntimeException(sprintf(
                'Git command failed: %s%s',
                implode(' ', $command),
                $stderr !== '' ? sprintf(' (%s)', $stderr) : '',
            ));
        }

        return $stdout;
    }

    /**
     * @param list<string> $command
     */
    protected function optionalFirstLine(array $command): ?string
    {
        [$exitCode, $stdout] = $this->execute($command);

        if ($exitCode !== 0 || $stdout === '') {
            return null;
        }

        $lines = preg_split('/\R+/', $stdout);
        $first = is_array($lines) ? trim((string) ($lines[0] ?? '')) : '';

        return $first !== '' ? $first : null;
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function execute(array $command): array
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf(
                'Unable to start git command: %s',
                implode(' ', $command),
            ));
        }

        $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';
        $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';

        if (is_resource($pipes[1] ?? null)) {
            fclose($pipes[1]);
        }

        if (is_resource($pipes[2] ?? null)) {
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);

        return [
            $exitCode,
            trim(is_string($stdout) ? $stdout : ''),
            trim(is_string($stderr) ? $stderr : ''),
        ];
    }
}
