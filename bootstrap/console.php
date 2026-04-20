<?php

declare(strict_types=1);

use App\Console\Kernel;
use App\Foundation\ApplicationFactory;

defined('MYXA_BASE_PATH') || define('MYXA_BASE_PATH', dirname(__DIR__));

require_once __DIR__ . '/helpers.php';
myxa_require_vendor_autoload(true);

set_exception_handler(static function (Throwable $exception): void {
    myxa_emergency_log($exception);
    myxa_emit_console_emergency($exception);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    myxa_emergency_log(sprintf(
        'Fatal error: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line'],
    ));
    myxa_emit_console_emergency(sprintf(
        'Fatal error: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line'],
    ));
});

try {
    return new Kernel(ApplicationFactory::create(base_path()));
} catch (Throwable $exception) {
    myxa_emergency_log($exception);
    myxa_emit_console_emergency($exception);
}
