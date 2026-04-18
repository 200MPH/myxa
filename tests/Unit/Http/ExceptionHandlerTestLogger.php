<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use Myxa\Logging\LogLevel;
use Myxa\Logging\LoggerInterface;

final class ExceptionHandlerTestLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->entries[] = [
            'level' => $level->value,
            'message' => $message,
            'context' => $context,
        ];
    }
}
