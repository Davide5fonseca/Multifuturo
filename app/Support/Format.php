<?php

namespace App\Support;

use App\Enums\BusinessType;

/**
 * Formatação de valores para apresentação (pt-PT).
 * Preços e áreas nunca se formatam nos Blades à mão — passa tudo por aqui.
 */
final class Format
{
    /** "785 000 €" — separador de milhares com espaço fino, sem decimais quando são zero. */
    public static function price(int|float|string|null $value, string $currency = 'EUR', ?BusinessType $businessType = null, bool $visible = true): string
    {
        if (! $visible || $value === null || $value === '') {
            return __('ui.property.price_on_request');
        }

        $number = (float) $value;
        $decimals = floor($number) == $number ? 0 : 2;
        $formatted = number_format($number, $decimals, ',', "\u{202F}"); // espaço fino não separável

        $symbol = match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => strtoupper($currency),
        };

        $out = "{$formatted}\u{00A0}{$symbol}";

        if ($businessType?->isRent()) {
            $out .= __('ui.property.per_month');
        }

        return $out;
    }

    /** "142 m²" */
    public static function area(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        $decimals = floor($number) == $number ? 0 : 1;

        return number_format($number, $decimals, ',', "\u{202F}")."\u{00A0}m²";
    }

    /** "T3" (ou "T0"). Null quando não há tipologia. */
    public static function typology(?int $bedrooms): ?string
    {
        return $bedrooms === null ? null : 'T'.$bedrooms;
    }

    /** Localização curta: "Estoril, Cascais" (freguesia, concelho) sem repetir. */
    public static function location(?string $locality, ?string $city, ?string $district = null): string
    {
        $parts = [];
        foreach ([$locality, $city, $district] as $part) {
            if ($part !== null && $part !== '' && ! in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return implode(', ', array_slice($parts, 0, 2));
    }
}
