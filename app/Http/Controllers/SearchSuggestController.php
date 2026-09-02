<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\PropertyCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sugestões da pesquisa do site: concelhos, freguesias e imóveis (título ou
 * referência) da carteira publicada, enquanto o visitante escreve. Só leitura,
 * em cache, com os URLs já montados para a finalidade escolhida (f=buy|rent).
 */
class SearchSuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $route = $request->query('f') === 'rent' ? 'rent' : 'buy';

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)).'%';

        $items = PropertyCache::remember('suggest:'.$route.':'.md5($term), function () use ($term, $route) {
            $ativo = Property::query()->active();
            $items = [];

            foreach ((clone $ativo)->whereNotNull('city')->whereRaw('LOWER(city) LIKE ?', [$term])->distinct()->orderBy('city')->limit(4)->pluck('city') as $city) {
                $items[] = [
                    'group' => __('ui.search.group_cities'),
                    'label' => $city,
                    'hint' => '',
                    'url' => route($route, ['concelho' => $city]),
                ];
            }

            foreach ((clone $ativo)->whereNotNull('locality')->whereRaw('LOWER(locality) LIKE ?', [$term])->select('locality', 'city')->distinct()->orderBy('locality')->limit(4)->get() as $l) {
                $items[] = [
                    'group' => __('ui.search.group_localities'),
                    'label' => $l->locality,
                    'hint' => (string) $l->city,
                    'url' => route($route, ['concelho' => $l->city, 'freguesia' => $l->locality]),
                ];
            }

            $props = (clone $ativo)
                ->where(fn ($w) => $w->whereRaw('LOWER(reference) LIKE ?', [$term])->orWhereRaw("LOWER(translations->'pt'->>'title') LIKE ?", [$term]))
                ->orderByRaw('crm_updated_at DESC NULLS LAST')
                ->limit(4)
                ->get();

            foreach ($props as $p) {
                $items[] = [
                    'group' => __('ui.search.group_properties'),
                    'label' => $p->title ?: (string) ($p->reference ?? $p->internal_id),
                    'hint' => trim(($p->reference ?? '').($p->city ? ' · '.$p->city : ''), ' ·'),
                    'url' => route('property.show', $p),
                ];
            }

            return array_slice($items, 0, 10);
        });

        return response()->json(['items' => $items]);
    }
}
