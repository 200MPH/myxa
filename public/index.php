<?php

declare(strict_types=1);

$kernel = require dirname(__DIR__) . '/bootstrap/http.php';
$kernel->handle()->send();
