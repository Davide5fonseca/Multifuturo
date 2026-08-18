<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt dinâmico. Fora de produção bloqueia tudo — evita que um ambiente
 * de staging seja indexado por engano. O Sitemap aponta para config('app.url').
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Allow: /',
                'Disallow: /livewire/',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : [
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
