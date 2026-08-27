<?php

namespace App\Filament\Resources\ReferencePrices\Tables;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferencePricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('city')
            ->columns([
                TextColumn::make('city')
                    ->label('Concelho')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('property_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => ReferencePriceResource::TYPES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('price_per_m2')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' ').' €/m²')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Fonte e data')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('property_type')
                    ->label('Tipo')
                    ->options(ReferencePriceResource::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
