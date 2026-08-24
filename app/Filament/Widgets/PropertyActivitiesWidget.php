<?php

namespace App\Filament\Widgets;

use App\Models\PropertyActivity;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Actualizações" — histórico do que a equipa andou a mexer nos imóveis:
 * novas fichas, alterações de preço, imóveis retirados. Alimentado
 * automaticamente pelo PropertyObserver.
 */
class PropertyActivitiesWidget extends TableWidget
{
    protected static ?string $heading = 'Actualizações';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(PropertyActivity::query()->with(['property', 'user'])->latest())
            ->paginated([8, 25])
            ->defaultPaginationPageOption(8)
            ->emptyStateHeading('Ainda sem alterações registadas')
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Utilizador')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? collect(explode(' ', $state))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') : '—')
                    ->tooltip(fn (PropertyActivity $record) => $record->user?->name)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                ImageColumn::make('property.cover_photo.url')
                    ->label('Propriedade')
                    ->disk(null)
                    ->defaultImageUrl(asset('images/placeholder-property.jpg'))
                    ->height(32),
                TextColumn::make('property.reference')
                    ->label('Referência')
                    ->placeholder('—')
                    ->url(fn (PropertyActivity $record) => $record->property ? route('filament.admin.resources.properties.edit', $record->property) : null),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PropertyActivity::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'price' => 'warning',
                        'status' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('detail')
                    ->label('Detalhes')
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
