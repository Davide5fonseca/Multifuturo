<?php

namespace App\Filament\Resources\PropertyAlerts;

use App\Filament\Resources\PropertyAlerts\Pages\ListPropertyAlerts;
use App\Filament\Resources\PropertyAlerts\Tables\PropertyAlertsTable;
use App\Models\PropertyAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Alertas de imóveis pedidos no site — só leitura e apagar. Quem os cria e
 * cancela é o visitante (confirmação e cancelamento por email); aqui vê-se
 * o que as pessoas procuram e apaga-se a pedido delas (RGPD).
 */
class PropertyAlertResource extends Resource
{
    protected static ?string $model = PropertyAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $modelLabel = 'alerta';

    protected static ?string $pluralModelLabel = 'Alertas de imóveis';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PropertyAlertsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropertyAlerts::route('/'),
        ];
    }
}
