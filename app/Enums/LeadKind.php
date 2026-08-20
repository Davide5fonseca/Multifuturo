<?php

namespace App\Enums;

/** Tipo de lead: quem procura casa vs. quem quer vender/arrendar (angariação). */
enum LeadKind: string
{
    case Buyer = 'buyer';
    case Listing = 'listing';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Comprador',
            self::Listing => 'Angariação',
        };
    }
}
