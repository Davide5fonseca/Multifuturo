<?php

namespace App\Enums;

enum LeadPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    /** Cores do Filament (badges). */
    public function color(): string
    {
        return match ($this) {
            self::Normal => 'warning',
            self::High => 'danger',
            self::Urgent => 'primary',
        };
    }
}
