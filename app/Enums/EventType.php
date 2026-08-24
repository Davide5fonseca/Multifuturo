<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de evento da agenda — a lista completa do CRM da CASAFARI, pela mesma
 * ordem do menu. A cor de cada tipo vive em hex() (usada no calendário e na
 * legenda como estilo inline, para não depender da compilação do Tailwind).
 */
enum EventType: string implements HasIcon, HasLabel
{
    case Call = 'call';                // Telefonema
    case Visit = 'visit';              // Visita a imóvel
    case Email = 'email';
    case Deed = 'deed';                // Escritura
    case Meeting = 'meeting';          // Reunião
    case Task = 'task';                // Tarefa
    case Reminder = 'reminder';        // Lembrete
    case Other = 'other';              // Outros
    case Arrival = 'arrival';          // Chegadas
    case Cpcv = 'cpcv';                // CPCV
    case ServiceDay = 'service_day';   // Dia de serviço
    case Offer = 'offer';              // Oferta

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Telefonema',
            self::Visit => 'Visita a imóvel',
            self::Email => 'Email',
            self::Deed => 'Escritura',
            self::Meeting => 'Reunião',
            self::Task => 'Tarefa',
            self::Reminder => 'Lembrete',
            self::Other => 'Outros',
            self::Arrival => 'Chegadas',
            self::Cpcv => 'CPCV',
            self::ServiceDay => 'Dia de serviço',
            self::Offer => 'Oferta',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Visit => 'heroicon-o-home-modern',
            self::Email => 'heroicon-o-envelope',
            self::Deed => 'heroicon-o-document-text',
            self::Meeting => 'heroicon-o-users',
            self::Task => 'heroicon-o-check-circle',
            self::Reminder => 'heroicon-o-bell',
            self::Other => 'heroicon-o-paper-clip',
            self::Arrival => 'heroicon-o-paper-airplane',
            self::Cpcv => 'heroicon-o-clipboard-document-check',
            self::ServiceDay => 'heroicon-o-briefcase',
            self::Offer => 'heroicon-o-map-pin',
        };
    }

    /** Cor de fundo no calendário e na legenda (texto branco por cima). */
    public function hex(): string
    {
        return match ($this) {
            self::Call => '#D97706',
            self::Visit => '#059669',
            self::Email => '#7C3AED',
            self::Deed => '#BE123C',
            self::Meeting => '#0284C7',
            self::Task => '#6B7248',
            self::Reminder => '#6B7280',
            self::Other => '#78716C',
            self::Arrival => '#0891B2',
            self::Cpcv => '#4F46E5',
            self::ServiceDay => '#0D9488',
            self::Offer => '#EA580C',
        };
    }

    /** Cor Filament (badges nas tabelas e na dashboard). */
    public function color(): string
    {
        return match ($this) {
            self::Call, self::Offer => 'warning',
            self::Visit, self::ServiceDay => 'success',
            self::Meeting, self::Email, self::Arrival => 'info',
            self::Task, self::Cpcv => 'primary',
            self::Deed => 'danger',
            self::Reminder, self::Other => 'gray',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getIcon(): string
    {
        return $this->icon();
    }
}
