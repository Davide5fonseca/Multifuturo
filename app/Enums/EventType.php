<?php

namespace App\Enums;

enum EventType: string
{
    case Call = 'call';
    case Visit = 'visit';
    case Meeting = 'meeting';
    case Task = 'task';
    case Reminder = 'reminder';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Telefonema',
            self::Visit => 'Visita',
            self::Meeting => 'Reunião',
            self::Task => 'Tarefa',
            self::Reminder => 'Lembrete',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Visit => 'heroicon-o-home-modern',
            self::Meeting => 'heroicon-o-users',
            self::Task => 'heroicon-o-check-circle',
            self::Reminder => 'heroicon-o-bell',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Call => 'warning',
            self::Visit => 'success',
            self::Meeting => 'info',
            self::Task => 'primary',
            self::Reminder => 'gray',
        };
    }
}
