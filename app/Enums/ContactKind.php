<?php

namespace App\Enums;

enum ContactKind: string
{
    case Buyer = 'buyer';
    case Owner = 'owner';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Comprador',
            self::Owner => 'Proprietário',
            self::Both => 'Ambos',
        };
    }
}
