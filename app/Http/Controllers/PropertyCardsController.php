<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Fragmento com cartões de imóveis para os slugs pedidos — serve os "vistos
 * recentemente", cujos slugs vivem no localStorage do visitante (o mesmo
 * padrão dos favoritos). Só imóveis publicados, pela ordem pedida, máx. 8.
 */
class PropertyCardsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $slugs = collect(explode(',', (string) $request->query('slugs', '')))
            ->map(fn ($s) => mb_substr(trim($s), 0, 191))
            ->filter(fn ($s) => $s !== '' && preg_match('/^[a-z0-9-]+$/', $s))
            ->unique()
            ->take(8)
            ->values();

        $properties = $slugs->isEmpty()
            ? collect()
            : Property::query()->active()->whereIn('slug', $slugs)->get()->sortBy(fn ($p) => $slugs->search($p->slug))->values();

        return view('partials.property-cards', ['properties' => $properties]);
    }
}
