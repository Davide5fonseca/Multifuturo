<?php

namespace App\Support;

use App\Models\Property;
use App\Models\ReferencePrice;

/**
 * Estimativa imediata de valor ("Quanto vale a minha casa?").
 *
 * valor = €/m² × área × fator do estado, com margem de ±10 % e arredondado
 * ao milhar. Nos terrenos o estado não conta (fator 1). O €/m² procura-se
 * por esta ordem:
 *   1. valor de referência da freguesia (reference_prices com locality), se
 *      a pessoa a indicou e existe para o tipo;
 *   2. valor de referência do concelho (manual ou importado do INE);
 *   3. mediana das nossas vendas publicadas com preço público no concelho,
 *      desde que haja pelo menos MIN_COMPARABLES;
 *   4. valor por omissão da agência (city = DEFAULT_CITY, "todos os
 *      concelhos") — a rede para o que não tem valor próprio; existe
 *      sobretudo para os terrenos, que o INE não publica.
 * Sem nenhum dos quatro, não há estimativa — o site convida ao pedido.
 *
 * A tabela inteira vai embutida na página e a conta repete-se em JavaScript
 * no browser (ver o componente Blade). Estrutura:
 *   concelho → ['types' => tipo → base, 'localities' => freguesia → tipo → base]
 *   base = ['ppm2' => float, 'source' => portfolio|reference|ine, 'n' => int]
 */
final class Valuation
{
    public const TYPES = ['apartment', 'house', 'land'];

    /** Valor de reference_prices.city que significa "todos os concelhos sem valor próprio". */
    public const DEFAULT_CITY = '*';

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
     * @return array<string, array{types: array<string, array{ppm2: float, source: string, n: int}>, localities: array<string, array<string, array{ppm2: float, source: string, n: int}>>}>
     */
    public static function table(): array
    {
        return PropertyCache::remember('valuation:table', function () {
            $table = [];

            foreach (self::fromPortfolio() as $city => $types) {
                $table[$city] = ['types' => $types, 'localities' => []];
            }

            // Os valores de referência sobrepõem-se à carteira. Os "todos os
            // concelhos" ficam na entrada DEFAULT_CITY, consultada em último.
            foreach (ReferencePrice::query()->orderBy('city')->orderBy('locality')->get() as $row) {
                $source = match (true) {
                    $row->city === self::DEFAULT_CITY => 'default',
                    $row->source === 'ine' => 'ine',
                    default => 'reference',
                };
                $base = ['ppm2' => (float) $row->price_per_m2, 'source' => $source, 'n' => 0];
                $table[$row->city] ??= ['types' => [], 'localities' => []];

                if ($row->locality === '') {
                    $table[$row->city]['types'][$row->property_type] = $base;
                } else {
                    $table[$row->city]['localities'][$row->locality][$row->property_type] = $base;
                }
            }

            ksort($table, SORT_NATURAL | SORT_FLAG_CASE);

            return $table;
        });
    }

    /**
     * @return array{min: int, mid: int, max: int, ppm2: float, source: string, n: int, place: string}|null
     */
    public static function estimate(string $city, string $type, float $area, string $condition = 'good', ?string $locality = null): ?array
    {
        $table = self::table();
        $entry = $table[$city] ?? null;

        if ($city === '' || $city === self::DEFAULT_CITY || $area <= 0 || ! isset(self::CONDITIONS[$condition])) {
            return null;
        }

        $base = null;
        $place = $city;

        if ($entry && $locality !== null && $locality !== '' && isset($entry['localities'][$locality][$type])) {
            $base = $entry['localities'][$locality][$type];
            $place = $locality.', '.$city;
        } elseif (isset($entry['types'][$type])) {
            $base = $entry['types'][$type];
        } elseif (isset($table[self::DEFAULT_CITY]['types'][$type])) {
            $base = $table[self::DEFAULT_CITY]['types'][$type];
            $place = '';
        }

        if (! $base) {
            return null;
        }

        // Num terreno o estado de conservação não diz nada.
        $factor = $type === 'land' ? 1.0 : self::CONDITIONS[$condition];
        $mid = $base['ppm2'] * $area * $factor;

        return [
            'min' => self::thousands($mid * (1 - self::MARGIN)),
            'mid' => self::thousands($mid),
            'max' => self::thousands($mid * (1 + self::MARGIN)),
            'ppm2' => $base['ppm2'],
            'source' => $base['source'],
            'n' => $base['n'],
            'place' => $place,
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
