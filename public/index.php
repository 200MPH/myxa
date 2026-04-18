<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/helpers.php';
require_once base_path('vendor/autoload.php');

if (class_exists(\App\Foundation\Environment::class)) {
    \App\Foundation\Environment::load(base_path('.env'));
}

if (class_exists(\App\Maintenance\MaintenanceMode::class)) {
    $maintenance = new \App\Maintenance\MaintenanceMode(base_path());

    if ($maintenance->isEnabled()) {
        (new \App\Maintenance\MaintenanceResponseFactory(base_path()))
            ->emitFromGlobals($maintenance->payload());

        return;
    }
}

set_exception_handler(static function (Throwable $exception): void {
    myxa_emergency_log($exception);
    myxa_emit_emergency_response();
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
    myxa_emit_emergency_response();
});

try {
    $kernel = require dirname(__DIR__) . '/bootstrap/http.php';
    $kernel->handle()->send();
} catch (Throwable $exception) {
    myxa_emergency_log($exception);
    myxa_emit_emergency_response();
}
