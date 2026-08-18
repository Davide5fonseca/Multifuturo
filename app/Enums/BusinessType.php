<?php

namespace App\Enums;

/**
 * Finalidade do imóvel. Os valores são os guardados na coluna business_type;
 * os slugs públicos (comprar/arrendar) e os rótulos vivem em métodos, para que
 * o mapeamento do feed e as rotas não dependam de strings soltas.
 */
enum BusinessType: string
{
    case Sale = 'sale';
    case Rent = 'rent';

    public function routeName(): string
    {
        return match ($this) {
            self::Sale => 'buy',
            self::Rent => 'rent',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('ui.business.sale'),
            self::Rent => __('ui.business.rent'),
        };
    }

    public static function fromRouteName(string $route): self
    {
        return match ($route) {
            'buy' => self::Sale,
            'rent' => self::Rent,
        };
    }
}
