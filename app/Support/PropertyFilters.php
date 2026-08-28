<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Os filtros da listagem, num só sítio: a listagem (/comprar, /arrendar)
 * aplica-os à pesquisa e os alertas de imóveis guardam-nos e reaplicam-nos
 * a cada envio. Se um filtro novo entrar aqui, entra nos dois.
 *
 * Critérios (todas as chaves opcionais):
 *   type, bedrooms (mínimo), city, locality, price_min, price_max,
 *   area_min, features (lista)
 */
final class PropertyFilters
{
    public const KEYS = ['type', 'bedrooms', 'city', 'locality', 'price_min', 'price_max', 'area_min', 'features'];

    /** Chave de critério → parâmetro do URL da listagem. */
    public const URL_PARAMS = [
        'type' => 'tipo', 'bedrooms' => 'tipologia', 'city' => 'concelho', 'locality' => 'freguesia',
        'price_min' => 'preco_min', 'price_max' => 'preco_max', 'area_min' => 'area_min', 'features' => 'caracteristicas',
    ];

    /**
     * Normaliza critérios vindos de fora (URL, formulário): só as chaves
     * conhecidas, limitadas e sem vazios, com as chaves ordenadas para que
     * dois pedidos iguais fiquem byte a byte iguais.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function sanitize(array $raw): array
    {
        $out = [];

        foreach (['type' => 64, 'city' => 96, 'locality' => 96] as $key => $max) {
            $value = mb_substr(trim((string) ($raw[$key] ?? '')), 0, $max);
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        $bedrooms = (string) ($raw['bedrooms'] ?? '');
        if (ctype_digit($bedrooms) && (int) $bedrooms <= 20) {
            $out['bedrooms'] = (int) $bedrooms;
        }

        foreach (['price_min', 'price_max', 'area_min'] as $key) {
            $digits = preg_replace('/\D+/', '', (string) ($raw[$key] ?? '')) ?? '';
            $digits = ltrim($digits, '0');
            if ($digits !== '' && strlen($digits) <= 12) {
                $out[$key] = (int) $digits;
            }
        }

        $features = is_array($raw['features'] ?? null) ? $raw['features'] : [];
        $features = array_values(array_unique(array_filter(array_map(
            fn ($f) => mb_substr(mb_strtolower(trim((string) $f)), 0, 96),
            array_slice($features, 0, 12),
        ))));
        if ($features !== []) {
            sort($features);
            $out['features'] = $features;
        }

        ksort($out);

        return $out;
    }

    /**
     * @param  Builder<Property>  $query
     * @param  array<string, mixed>  $c
     * @return Builder<Property>
     */
    public static function apply(Builder $query, array $c): Builder
    {
        if (filled($c['type'] ?? null)) {
            $query->whereRaw('LOWER(property_type) = ?', [mb_strtolower((string) $c['type'])]);
        }
        if (filled($c['bedrooms'] ?? null)) {
            $query->where('bedrooms', '>=', (int) $c['bedrooms']);
        }
        if (filled($c['city'] ?? null)) {
            $query->whereRaw('LOWER(city) = ?', [mb_strtolower((string) $c['city'])]);
        }
        if (filled($c['locality'] ?? null)) {
            $query->whereRaw('LOWER(locality) = ?', [mb_strtolower((string) $c['locality'])]);
        }
        if (filled($c['price_min'] ?? null)) {
            $query->where('price', '>=', (int) $c['price_min']);
        }
        if (filled($c['price_max'] ?? null)) {
            $query->where('price', '<=', (int) $c['price_max']);
        }
        if (filled($c['area_min'] ?? null)) {
            $min = (int) $c['area_min'];
            $query->where(fn (Builder $w) => $w->where('house_area', '>=', $min)->orWhere('gross_area', '>=', $min));
        }
        if (! empty($c['features']) && is_array($c['features'])) {
            $query->withFeatures($c['features']);
        }

        return $query;
    }

    /**
     * Frase curta com os critérios, para o site, o email e o backoffice:
     * "Venda · Sintra, Colares · Apartamento · T3+ · 150 000 € – 300 000 € · ≥ 80 m² · piscina".
     *
     * @param  array<string, mixed>  $c
     */
    public static function summary(array $c, string $listing): string
    {
        $parts = [__('ui.listing.'.($listing === 'rent' ? 'rent_eyebrow' : 'buy_eyebrow'))];

        $place = Format::location($c['locality'] ?? null, $c['city'] ?? null);
        if ($place !== '') {
            $parts[] = $place;
        }
        if (filled($c['type'] ?? null)) {
            $parts[] = (string) $c['type'];
        }
        if (filled($c['bedrooms'] ?? null)) {
            $parts[] = 'T'.(int) $c['bedrooms'].'+';
        }

        $min = filled($c['price_min'] ?? null) ? Format::price((int) $c['price_min']) : null;
        $max = filled($c['price_max'] ?? null) ? Format::price((int) $c['price_max']) : null;
        if ($min && $max) {
            $parts[] = "{$min} – {$max}";
        } elseif ($min) {
            $parts[] = "≥ {$min}";
        } elseif ($max) {
            $parts[] = "≤ {$max}";
        }

        if (filled($c['area_min'] ?? null)) {
            $parts[] = '≥ '.Format::area((int) $c['area_min']);
        }
        if (! empty($c['features']) && is_array($c['features'])) {
            $parts[] = implode(', ', $c['features']);
        }

        // Sem nenhum filtro: "Venda · todos os imóveis", em vez de "Venda" sozinho.
        if (count($parts) === 1) {
            $parts[] = __('ui.alerts.all_properties');
        }

        return implode(' · ', $parts);
    }

    /**
     * Parâmetros do URL da listagem equivalentes aos critérios.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public static function urlParams(array $c): array
    {
        $params = [];

        foreach (self::URL_PARAMS as $key => $param) {
            if (isset($c[$key]) && $c[$key] !== '' && $c[$key] !== []) {
                $params[$param] = $c[$key];
            }
        }

        return $params;
    }
}
