<?php

declare(strict_types=1);

namespace Test\Unit\Version;

final class GitVersionResolverTestState
{
    public static bool $procOpenFails = false;

    /** @var list<array{exit: int, stdout: string, stderr: string}> */
    public static array $responses = [];

    /** @var array<int, int> */
    public static array $exitCodes = [];
}
