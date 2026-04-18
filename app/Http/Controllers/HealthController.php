<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Version\ApplicationVersion;
use Myxa\Http\Request;

final class HealthController
{
    public function __invoke(Request $request, ApplicationVersion $version): array
    {
        $details = $version->details();

        return [
            'ok' => true,
            'framework' => 'myxa',
            'version' => $details['version'],
            'version_source' => $details['source'],
            'method' => $request->method(),
            'path' => $request->path(),
            'timestamp' => date(DATE_ATOM),
        ];
    }
}
