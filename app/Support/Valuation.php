<?php

namespace App\Support;

use App\Models\Property;
use App\Models\ReferencePrice;

/**
 * Estimativa imediata de valor ("Quanto vale a minha casa?").
 *
 * valor = €/m² (concelho × tipo) × área × fator do estado, com margem de
 * ±10 % e arredondado ao milhar. O €/m² vem, por esta ordem:
 *   1. valores de referência escritos no backoffice (reference_prices);
 *   2. mediana das nossas vendas publicadas com preço público no concelho,
 *      desde que haja pelo menos MIN_COMPARABLES.
 * Sem nenhum dos dois, não há estimativa — o site convida ao pedido.
 *
 * A tabela inteira é pequena (concelhos × 3 tipos) e vai embutida na página;
 * a conta repete-se em JavaScript no browser (ver o componente Blade).
 */
final class Valuation
{
    public const TYPES = ['apartment', 'house', 'land'];

    /** Fator pelo estado de conservação. */
    public const CONDITIONS = ['new' => 1.08, 'good' => 1.0, 'renovate' => 0.85];

    /** Margem do intervalo apresentado (±). */
    public const MARGIN = 0.10;

    /** Vendas necessárias num concelho para a carteira servir de base. */
    public const MIN_COMPARABLES = 3;

    /** Tipos das fichas → grupo do simulador. O que não está aqui (lojas, prédios…) fica de fora. */
    private const TYPE_GROUPS = [
        'apartamento' => 'apartment', 'penthouse' => 'apartment',
        'moradia' => 'house', 'moradia em banda' => 'house', 'casa de campo' => 'house', 'chalet' => 'house', 'quinta' => 'house',
        'terreno' => 'land', 'terreno rústico' => 'land', 'terreno urbano' => 'land',
    ];

    /**
     * @return array<string, array<string, array{ppm2: float, source: string, n: int}>> concelho → tipo → base
     */
    public static function table(): array
    {
        return PropertyCache::remember('valuation:table', function () {
            $table = self::fromPortfolio();

            foreach (ReferencePrice::query()->get() as $row) {
                $table[$row->city][$row->property_type] = ['ppm2' => (float) $row->price_per_m2, 'source' => 'reference', 'n' => 0];
            }

            ksort($table, SORT_NATURAL | SORT_FLAG_CASE);

            return $table;
        });
    }

    /**
     * @return array{min: int, mid: int, max: int, ppm2: float, source: string, n: int}|null
     */
    public static function estimate(string $city, string $type, float $area, string $condition = 'good'): ?array
    {
        $base = self::table()[$city][$type] ?? null;

        if (! $base || $area <= 0 || ! isset(self::CONDITIONS[$condition])) {
            return null;
        }

        $mid = $base['ppm2'] * $area * self::CONDITIONS[$condition];

        return [
            'min' => self::thousands($mid * (1 - self::MARGIN)),
            'mid' => self::thousands($mid),
            'max' => self::thousands($mid * (1 + self::MARGIN)),
            'ppm2' => $base['ppm2'],
            'source' => $base['source'],
            'n' => $base['n'],
        ];
    }

    public static function group(?string $propertyType): ?string
    {
        return self::TYPE_GROUPS[mb_strtolower(trim((string) $propertyType))] ?? null;
    }

    /** @return array<string, array<string, array{ppm2: float, source: string, n: int}>> */
    private static function fromPortfolio(): array
    {
        $rows = Property::query()->active()->forSale()
            ->where('price_visible', true)
            ->where('price', '>', 0)
            ->whereNotNull('city')
            ->whereNotNull('property_type')
            ->get(['city', 'property_type', 'price', 'house_area', 'gross_area', 'plot_area']);

        $samples = [];

        foreach ($rows as $property) {
            $type = self::group($property->property_type);

            if (! $type) {
                continue;
            }

            $area = $type === 'land'
                ? (float) $property->plot_area
                : (float) ($property->house_area ?: $property->gross_area);

            if ($area < 10) {
                continue;
            }

            $samples[$property->city][$type][] = (float) $property->price / $area;
        }

        $table = [];

        foreach ($samples as $city => $types) {
            foreach ($types as $type => $values) {
                if (count($values) < self::MIN_COMPARABLES) {
                    continue;
                }

                $table[$city][$type] = ['ppm2' => round(self::median($values), 2), 'source' => 'portfolio', 'n' => count($values)];
            }
        }

        return $table;
    }

    /** Arredonda ao milhar: "247 350 €" fingiria uma precisão que a conta não tem. */
    private static function thousands(float $value): int
    {
        return (int) (round($value / 1000) * 1000);
    }

    /** @param  list<float>  $values */
    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);

        return $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }
}
