<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Zone;
use App\Support\PropertyCache;
use App\Support\Zones;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Páginas de zona: /zonas, /zonas/{concelho}, /zonas/{concelho}/{freguesia}.
 * Editorial (tabela zones) + imóveis dessa zona, paginados. Ligam-se às
 * listagens filtradas para comprar/arrendar na mesma zona.
 */
class ZoneController extends Controller
{
    public const PER_PAGE = 12;

    public function index(): View
    {
        return view('pages.zones', ['cities' => Zones::cities()]);
    }

    public function city(Request $request, string $city): View
    {
        $cityName = Zones::cityName($city) ?? abort(404);

        return $this->zonePage($request, $cityName, null, $city, null);
    }

    public function locality(Request $request, string $city, string $locality): View
    {
        $cityName = Zones::cityName($city) ?? abort(404);
        $localityName = Zones::localityName($cityName, $locality) ?? abort(404);

        return $this->zonePage($request, $cityName, $localityName, $city, $locality);
    }

    private function zonePage(Request $request, string $cityName, ?string $localityName, string $citySlug, ?string $localitySlug): View
    {
        $page = max(1, (int) $request->query('page', 1));

        $editorial = PropertyCache::remember("zone:editorial:{$citySlug}:".($localitySlug ?? '-'), fn () => Zone::query()
            ->where('city_slug', $citySlug)
            ->where('locality_slug', $localitySlug)
            ->where('is_published', true)
            ->first());

        $properties = PropertyCache::remember("zone:props:{$citySlug}:".($localitySlug ?? '-').":{$page}", fn () => Property::query()
            ->active()
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($cityName)])
            ->when($localityName, fn ($q) => $q->whereRaw('LOWER(locality) = ?', [mb_strtolower($localityName)]))
            ->orderByRaw('crm_updated_at DESC NULLS LAST')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withPath(url()->current()));

        return view('pages.zone', [
            'cityName' => $cityName,
            'localityName' => $localityName,
            'citySlug' => $citySlug,
            'localitySlug' => $localitySlug,
            'zoneName' => $localityName ? "{$localityName}, {$cityName}" : $cityName,
            'editorial' => $editorial,
            'localities' => $localityName ? collect() : Zones::localities($cityName),
            'properties' => $properties,
        ]);
    }
}
