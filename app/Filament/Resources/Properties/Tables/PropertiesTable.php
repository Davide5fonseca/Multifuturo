<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Models\Property;
use App\Support\Format;
use App\Support\PropertyCache;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Lista de imóveis do backoffice: colunas essenciais, filtros por finalidade e
 * estado, e interruptores rápidos de publicado/destaque.
 */
class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover_photo.url')
                    ->label('')
                    ->disk(null)
                    ->defaultImageUrl(asset('images/placeholder-property.jpg'))
                    ->square(),
                TextColumn::make('reference')
                    ->label('Referência')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('title')
                    ->label('Título')
                    ->state(fn ($record) => $record->title)
                    ->limit(40)
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("LOWER(translations->'pt'->>'title') LIKE ?", ['%'.mb_strtolower($search).'%'])),
                TextColumn::make('business_type')
                    ->label('Finalidade')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->value === 'sale' ? 'Venda' : 'Arrendamento')
                    ->color(fn ($state) => $state->value === 'sale' ? 'primary' : 'info'),
                TextColumn::make('city')
                    ->label('Concelho')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Preço')
                    ->state(fn ($record) => Format::price($record->price, $record->currency, $record->business_type))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Publicado')
                    ->afterStateUpdated(fn () => PropertyCache::flush()),
                ToggleColumn::make('is_featured')
                    ->label('Destaque')
                    ->afterStateUpdated(fn () => PropertyCache::flush()),
                IconColumn::make('is_exclusive')
                    ->label('Exclusivo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('business_type')
                    ->label('Finalidade')
                    ->options(['sale' => 'Venda', 'rent' => 'Arrendamento']),
                TernaryFilter::make('is_active')
                    ->label('Publicado'),
                TernaryFilter::make('is_featured')
                    ->label('Em destaque'),
                SelectFilter::make('city')
                    ->label('Concelho')
                    ->options(fn () => Property::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => PropertyCache::flush()),
                ]),
            ]);
    }
}
