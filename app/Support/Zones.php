<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Zonas derivadas da carteira ativa: concelhos e freguesias com contagens.
 * Tudo em cache (invalidada no sync). Slugs públicos = Str::slug(nome).
 */
final class Zones
{
    /**
     * @return Collection<int, array{name: string, slug: string, count: int, sale: int, rent: int}>
     */
    public static function cities(): Collection
    {
        return PropertyCache::remember('zones:cities', function () {
            return Property::query()->active()
                ->whereNotNull('city')
                ->selectRaw("city, COUNT(*) AS total, SUM(CASE WHEN business_type = 'sale' THEN 1 ELSE 0 END) AS sale, SUM(CASE WHEN business_type = 'rent' THEN 1 ELSE 0 END) AS rent")
                ->groupBy('city')
                ->orderByDesc('total')
                ->orderBy('city')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->city,
                    'slug' => Str::slug($row->city),
                    'count' => (int) $row->total,
                    'sale' => (int) $row->sale,
                    'rent' => (int) $row->rent,
                ])
                ->values();
        });
    }

    /**
     * @return Collection<int, array{name: string, slug: string, count: int}>
     */
    public static function localities(string $cityName): Collection
    {
        return PropertyCache::remember('zones:localities:'.Str::slug($cityName), function () use ($cityName) {
            return Property::query()->active()
                ->whereRaw('LOWER(city) = ?', [mb_strtolower($cityName)])
                ->whereNotNull('locality')
                ->selectRaw('locality, COUNT(*) AS total')
                ->groupBy('locality')
                ->orderByDesc('total')
                ->orderBy('locality')
                ->get()
                ->map(fn ($row) => ['name' => $row->locality, 'slug' => Str::slug($row->locality), 'count' => (int) $row->total])
                ->values();
        });
    }

    /** Nome real do concelho a partir do slug público (ou null se não existir na carteira). */
    public static function cityName(string $slug): ?string
    {
        return self::cities()->firstWhere('slug', $slug)['name'] ?? null;
    }

    public static function localityName(string $cityName, string $slug): ?string
    {
        return self::localities($cityName)->firstWhere('slug', $slug)['name'] ?? null;
    }
}
