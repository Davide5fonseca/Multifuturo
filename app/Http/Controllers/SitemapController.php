<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Sitemap XML dinâmico. Todos os URLs derivam de config('app.url') através de
 * route()/url(); nada é hardcoded. Na Fase 4 passa a incluir apenas os imóveis
 * ativos (is_active = true) e as páginas de zona.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('buy'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('rent'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ];

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
