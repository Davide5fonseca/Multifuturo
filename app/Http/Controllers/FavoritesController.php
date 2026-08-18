<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Favoritos: os slugs vivem no localStorage do visitante (sem registo). A
 * página lê-os no browser e recarrega com ?slugs=a,b,c; o servidor devolve os
 * cartões desses imóveis (só ativos, máx. 60). Nada é guardado no servidor.
 */
class FavoritesController extends Controller
{
    public function index(Request $request): View
    {
        $slugs = collect(explode(',', (string) $request->query('slugs', '')))
            ->map(fn ($s) => mb_substr(trim($s), 0, 191))
            ->filter(fn ($s) => $s !== '' && preg_match('/^[a-z0-9-]+$/', $s))
            ->unique()
            ->take(60)
            ->values();

        $properties = $slugs->isEmpty()
            ? collect()
            : Property::query()->active()->whereIn('slug', $slugs)->get()->sortBy(fn ($p) => $slugs->search($p->slug))->values();

        return view('pages.favorites', [
            'properties' => $properties,
            'requested' => $request->has('slugs'),
        ]);
    }
}
