<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Myxa\Http\Response;
use Myxa\Support\Html\Html;

final class VueTestController
{
    public function __invoke(Html $html): Response
    {
        $scaffolded = is_file(base_path('resources/frontend/app.js'))
            && is_file(base_path('resources/frontend/components/CounterWidget.vue'))
            && is_file(base_path('vite.config.mjs'))
            && is_file(base_path('package.json'));
        $built = is_file(public_path('assets/frontend/app.js'));

        return (new Response())->html($html->renderPage(
            'pages/vue-test',
            [
                'scaffolded' => $scaffolded,
                'built' => $built,
                'installCommand' => './myxa frontend:install vue --npm',
                'buildCommand' => 'npm run frontend:build',
                'watchCommand' => 'npm run frontend:watch',
            ],
            'layouts/app',
            [
                'title' => 'Vue Test | Myxa',
                'faviconPath' => '/assets/images/myxa-mark.svg',
            ],
        ));
    }
}
