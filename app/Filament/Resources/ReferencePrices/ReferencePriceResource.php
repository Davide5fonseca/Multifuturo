<?php

namespace App\Filament\Resources\ReferencePrices;

use App\Filament\Resources\ReferencePrices\Pages\CreateReferencePrice;
use App\Filament\Resources\ReferencePrices\Pages\EditReferencePrice;
use App\Filament\Resources\ReferencePrices\Pages\ListReferencePrices;
use App\Filament\Resources\ReferencePrices\Schemas\ReferencePriceForm;
use App\Filament\Resources\ReferencePrices\Tables\ReferencePricesTable;
use App\Models\ReferencePrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * €/m² por concelho e tipo — a base do simulador "Quanto vale a minha casa?".
 */
class ReferencePriceResource extends Resource
{
    protected static ?string $model = ReferencePrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $modelLabel = 'valor de referência';

    protected static ?string $pluralModelLabel = 'Valores de referência';

    protected static ?int $navigationSort = 4;

    /** Rótulos dos tipos do simulador, como aparecem no site. */
    public const TYPES = ['apartment' => 'Apartamento', 'house' => 'Moradia', 'land' => 'Terreno'];

    public static function form(Schema $schema): Schema
    {
        return ReferencePriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferencePricesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferencePrices::route('/'),
            'create' => CreateReferencePrice::route('/create'),
            'edit' => EditReferencePrice::route('/{record}/edit'),
        ];
    }
}
