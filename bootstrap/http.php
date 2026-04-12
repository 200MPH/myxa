<?php

declare(strict_types=1);

use App\Foundation\ApplicationFactory;
use App\Http\Kernel;

defined('MYXA_BASE_PATH') || define('MYXA_BASE_PATH', dirname(__DIR__));

require_once __DIR__ . '/helpers.php';
require_once base_path('vendor/autoload.php');

return new Kernel(ApplicationFactory::create(base_path()));
