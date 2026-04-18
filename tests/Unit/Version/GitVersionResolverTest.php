<?php

declare(strict_types=1);

namespace App\Version {
    /**
     * @param list<string> $command
     * @param array<int, mixed> $descriptorSpec
     * @param array<int, resource> $pipes
     * @return resource|false
     */
    function proc_open(array $command, array $descriptorSpec, &$pipes)
    {
        if (\Test\Unit\Version\GitVersionResolverTestState::$procOpenFails) {
            return false;
        }

        if (\Test\Unit\Version\GitVersionResolverTestState::$responses === []) {
            return \proc_open($command, $descriptorSpec, $pipes);
        }

        $response = array_shift(\Test\Unit\Version\GitVersionResolverTestState::$responses);

        if (!is_array($response)) {
            return false;
        }

        $stdout = fopen('php://temp', 'w+b');
        $stderr = fopen('php://temp', 'w+b');

        if (!is_resource($stdout) || !is_resource($stderr)) {
            return false;
        }

        fwrite($stdout, $response['stdout']);
        fwrite($stderr, $response['stderr']);
        rewind($stdout);
        rewind($stderr);

        $pipes = [
            1 => $stdout,
            2 => $stderr,
        ];

        $process = fopen('php://temp', 'w+b');

        if (!is_resource($process)) {
            return false;
        }

        \Test\Unit\Version\GitVersionResolverTestState::$exitCodes[get_resource_id($process)] = $response['exit'];

        return $process;
    }

    /**
     * @param resource $process
     */
    function proc_close($process): int
    {
        $id = get_resource_id($process);

        if (array_key_exists($id, \Test\Unit\Version\GitVersionResolverTestState::$exitCodes)) {
            $exitCode = \Test\Unit\Version\GitVersionResolverTestState::$exitCodes[$id];
            unset(\Test\Unit\Version\GitVersionResolverTestState::$exitCodes[$id]);
            \fclose($process);

            return $exitCode;
        }

        return \proc_close($process);
    }
}

namespace Test\Unit\Version {

    use App\Version\GitVersionResolver;
    use PHPUnit\Framework\Attributes\CoversClass;
    use RuntimeException;
    use Test\TestCase;

    #[CoversClass(GitVersionResolver::class)]
    final class GitVersionResolverTest extends TestCase
    {
        protected function tearDown(): void
        {
            GitVersionResolverTestState::$procOpenFails = false;
            GitVersionResolverTestState::$responses = [];
            GitVersionResolverTestState::$exitCodes = [];

            parent::tearDown();
        }

        public function testResolveReturnsVersionMetadataFromGitCommands(): void
        {
            GitVersionResolverTestState::$responses = [
                ['exit' => 0, 'stdout' => 'v1.2.3-4-gabc123-dirty', 'stderr' => ''],
                ['exit' => 0, 'stdout' => "v1.2.3\nv1.2.2", 'stderr' => ''],
                ['exit' => 0, 'stdout' => 'abcdef1234567890abcdef1234567890abcdef12', 'stderr' => ''],
                ['exit' => 0, 'stdout' => 'abcdef1', 'stderr' => ''],
            ];

            $resolver = new GitVersionResolver();
            $resolved = $resolver->resolve('/tmp/project');

            self::assertSame('v1.2.3', $resolved['version']);
            self::assertSame('v1.2.3-4-gabc123-dirty', $resolved['describe']);
            self::assertSame('v1.2.3', $resolved['tag']);
            self::assertSame('abcdef1234567890abcdef1234567890abcdef12', $resolved['commit']);
            self::assertSame('abcdef1', $resolved['short_commit']);
            self::assertTrue($resolved['dirty']);
        }

        public function testResolveFallsBackToDescribeWhenHeadHasNoTag(): void
        {
            GitVersionResolverTestState::$responses = [
                ['exit' => 0, 'stdout' => 'abc1234', 'stderr' => ''],
                ['exit' => 0, 'stdout' => '', 'stderr' => ''],
                ['exit' => 0, 'stdout' => 'abcdef1234567890abcdef1234567890abcdef12', 'stderr' => ''],
                ['exit' => 0, 'stdout' => 'abc1234', 'stderr' => ''],
            ];

            $resolver = new GitVersionResolver();
            $resolved = $resolver->resolve('/tmp/project');

            self::assertSame('abc1234', $resolved['version']);
            self::assertNull($resolved['tag']);
            self::assertFalse($resolved['dirty']);
        }

        public function testRunThrowsWhenGitCommandFails(): void
        {
            GitVersionResolverTestState::$responses = [
                ['exit' => 1, 'stdout' => '', 'stderr' => 'fatal: not a git repository'],
            ];

            $resolver = new class extends GitVersionResolver {
                /** @param list<string> $command */
                public function callRun(array $command): string
                {
                    return $this->run($command);
                }
            };

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('fatal: not a git repository');

            $resolver->callRun(['git', 'status']);
        }

        public function testOptionalFirstLineReturnsNullForFailuresAndBlankOutput(): void
        {
            $resolver = new class extends GitVersionResolver {
                /** @param list<string> $command */
                public function callOptionalFirstLine(array $command): ?string
                {
                    return $this->optionalFirstLine($command);
                }
            };

            GitVersionResolverTestState::$responses = [
                ['exit' => 1, 'stdout' => '', 'stderr' => 'fatal'],
                ['exit' => 0, 'stdout' => "\n\n", 'stderr' => ''],
            ];

            self::assertNull($resolver->callOptionalFirstLine(['git', 'tag']));
            self::assertNull($resolver->callOptionalFirstLine(['git', 'tag']));
        }

        public function testResolveThrowsWhenGitProcessCannotStart(): void
        {
            GitVersionResolverTestState::$procOpenFails = true;

            $resolver = new GitVersionResolver();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to start git command');

            $resolver->resolve('/tmp/project');
        }
    }
}
