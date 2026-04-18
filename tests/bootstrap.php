<?php

declare(strict_types=1);

defined('MYXA_BASE_PATH') || define('MYXA_BASE_PATH', dirname(__DIR__));

require_once MYXA_BASE_PATH . '/bootstrap/helpers.php';
require_once MYXA_BASE_PATH . '/vendor/autoload.php';
require_once MYXA_BASE_PATH . '/tests/Support/CommandFailureOverrides.php';

date_default_timezone_set('UTC');
