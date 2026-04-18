<?php

declare(strict_types=1);

namespace App\Maintenance {

    function file_put_contents(string $filename, mixed $data, int $flags = 0): int|false
    {
        if (isset($GLOBALS['myxa.maintenance.file_put_contents_override'])) {
            return $GLOBALS['myxa.maintenance.file_put_contents_override']($filename, $data, $flags);
        }

        return \file_put_contents($filename, $data, $flags);
    }

    function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        if (isset($GLOBALS['myxa.maintenance.json_encode_override'])) {
            return $GLOBALS['myxa.maintenance.json_encode_override']($value, $flags, $depth);
        }

        return \json_encode($value, $flags, $depth);
    }

    function flock(mixed $stream, int $operation, ?int &$wouldBlock = null): bool
    {
        if (isset($GLOBALS['myxa.maintenance.flock_override'])) {
            return $GLOBALS['myxa.maintenance.flock_override']($stream, $operation, $wouldBlock);
        }

        return \flock($stream, $operation, $wouldBlock);
    }

    function is_dir(string $filename): bool
    {
        if (isset($GLOBALS['myxa.maintenance.is_dir_override'])) {
            return $GLOBALS['myxa.maintenance.is_dir_override']($filename);
        }

        return \is_dir($filename);
    }

    function unlink(string $filename): bool
    {
        if (isset($GLOBALS['myxa.maintenance.unlink_override'])) {
            return $GLOBALS['myxa.maintenance.unlink_override']($filename);
        }

        return \unlink($filename);
    }
}

namespace App\Routing {

    function unlink(string $filename): bool
    {
        if (isset($GLOBALS['myxa.route_cache.unlink_override'])) {
            return $GLOBALS['myxa.route_cache.unlink_override']($filename);
        }

        return \unlink($filename);
    }
}
