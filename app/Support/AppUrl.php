<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Todos os URLs absolutos (canonical, sitemap, Open Graph, JSON-LD, emails,
 * jobs em queue) derivam de config('app.url') — nunca do cabeçalho Host do
 * pedido, que é controlado pelo cliente.
 */
final class AppUrl
{
    public static function forceFromConfig(): void
    {
        $url = (string) config('app.url');

        if ($url === '') {
            return;
        }

        URL::forceRootUrl($url);

        if ($scheme = parse_url($url, PHP_URL_SCHEME)) {
            URL::forceScheme($scheme);
        }
    }
}
