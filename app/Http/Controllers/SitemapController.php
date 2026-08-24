<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\Locales;
use App\Support\PropertyCache;
use App\Support\Zones;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Sitemap XML dinâmico. Todos os URLs derivam de config('app.url') através de
 * route()/url(); nada é hardcoded. Inclui APENAS imóveis ativos, as zonas
 * derivadas da carteira e as páginas estáticas com conteúdo.
 *
 * Multilingue: uma entrada por idioma ligado, para o Google indexar as duas
 * versões (as etiquetas hreflang de cada página ligam-nas entre si).
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = PropertyCache::remember('sitemap', function () {
            $urls = [];

            foreach (Locales::enabled() as $locale) {
                $urls = [...$urls, ...$this->urlsForLocale($locale)];
            }

            // Repor o idioma por omissão nos route() seguintes deste pedido.
            URL::defaults(['locale' => Locales::default()]);

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function urlsForLocale(string $locale): array
    {
        URL::defaults(['locale' => $locale]);

        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('buy'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('rent'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('zones.index'), 'changefreq' => 'weekly', 'priority' => '0.6'],
            ['loc' => route('valuation'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        foreach (Zones::cities() as $city) {
            $urls[] = ['loc' => route('zones.city', $city['slug']), 'changefreq' => 'weekly', 'priority' => '0.7'];
            foreach (Zones::localities($city['name']) as $locality) {
                $urls[] = ['loc' => route('zones.locality', [$city['slug'], $locality['slug']]), 'changefreq' => 'weekly', 'priority' => '0.6'];
            }
        }

        Property::query()->active()->select(['id', 'slug', 'crm_updated_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($properties) use (&$urls) {
                foreach ($properties as $p) {
                    $urls[] = [
                        'loc' => route('property.show', $p),
                        'lastmod' => ($p->crm_updated_at ?? $p->updated_at)?->toDateString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                }
            });

        return $urls;
    }
}
