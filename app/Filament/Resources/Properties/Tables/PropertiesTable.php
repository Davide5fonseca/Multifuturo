<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\Format;
use App\Support\PropertyCache;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Lista de imóveis do backoffice, com as mesmas colunas da grelha do CRM:
 * Referência · Foto · Tipo · Concelho · Zona · Quarto(s) · Preço · Chaves ·
 * Angariador · Visualizar · Estado · Etiquetas.
 *
 * TODAS as colunas podem ser escondidas no menu "Colunas" — a pedido do
 * cliente, sem predefinição trancada. As doze do CRM abrem visíveis; as que a
 * grelha do CRM não tinha (título, finalidade, destaque, publicado,
 * atualizado) abrem escondidas. "Repor" volta a este arranjo.
 *
 * No telemóvel ficam só Referência, Foto, Preço e Estado; as restantes vão
 * aparecendo à medida que há largura (md, lg, xl). Doze colunas num ecrã de
 * 390px dariam 1400px de tabela para arrastar de lado.
 */
class PropertiesTable
{
    /** Só 'asc' e 'desc' entram no SQL das ordenações por jsonb. */
    private static function direction(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'asc' : 'desc';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Referência')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                ImageColumn::make('cover_photo.url')
                    ->label('Foto')
                    ->toggleable()
                    // As fotos do backoffice são "/storage/…": o Filament só mostra URLs completos.
                    ->getStateUsing(fn (Property $record) => $record->coverPhotoUrl())
                    ->disk(null)
                    ->defaultImageUrl(asset('images/placeholder-property.jpg'))
                    ->square()
                    ->visibleFrom('sm'),

                TextColumn::make('property_type')
                    ->label('Tipo')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('city')
                    ->label('Concelho')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('locality')
                    ->label('Zona')
                    ->toggleable()
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('lg'),

                TextColumn::make('bedrooms')
                    ->label('Quarto(s)')
                    ->toggleable()
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('lg'),

                TextColumn::make('price')
                    ->label('Preço')
                    ->toggleable()
                    ->state(fn (Property $record) => Format::price($record->price, $record->currency, $record->business_type, $record->price_visible))
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('keys')
                    ->label('Chaves')
                    ->toggleable()
                    ->state(fn (Property $record) => (bool) data_get($record->admin, 'keys.has'))
                    ->boolean()
                    ->tooltip(fn (Property $record) => data_get($record->admin, 'keys.notes'))
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(
                        "COALESCE((admin->'keys'->>'has')::boolean, false) ".self::direction($direction)
                    ))
                    ->visibleFrom('lg'),

                TextColumn::make('broker.name')
                    ->label('Angariador')
                    ->toggleable()
                    ->state(fn (Property $record) => data_get($record->broker, 'name'))
                    ->placeholder('—')
                    ->visibleFrom('xl'),

                TextColumn::make('view')
                    ->label('Visualizar')
                    ->toggleable()
                    // Só abre o que o site mostra: vendidas, retiradas ou fora de
                    // mercado respondem 410, não vale a pena oferecer o link.
                    ->state(fn (Property $record) => $record->isPublishable() ? 'Ver no site' : '—')
                    ->url(fn (Property $record) => $record->isPublishable() ? route('property.show', $record) : null, shouldOpenInNewTab: true)
                    ->icon(fn (Property $record) => $record->isPublishable() ? 'heroicon-m-arrow-top-right-on-square' : null)
                    ->color(fn (Property $record) => $record->isPublishable() ? 'primary' : 'gray')
                    ->visibleFrom('xl'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->toggleable()
                    ->badge()
                    ->state(fn (Property $record) => match (true) {
                        $record->trashed() => 'Na reciclagem',
                        $record->is_sold => 'Vendida',
                        $record->off_market => 'Fora de mercado',
                        $record->isInactive() => 'Inativa',
                        $record->isPending() => 'Pendente',
                        ! $record->is_active => 'Retirada',
                        default => 'Publicada',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Publicada' => 'success',
                        'Vendida', 'Inativa', 'Na reciclagem' => 'danger',
                        'Fora de mercado', 'Pendente' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Property $record) => $record->status_reason)
                    ->sortable(['is_active', 'is_sold', 'off_market']),

                TextColumn::make('tags')
                    ->label('Etiquetas')
                    ->toggleable()
                    ->state(fn (Property $record) => data_get($record->admin, 'tags', []))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable(query: fn ($query, string $search) => $query->whereRaw(
                        "admin->'tags' @> ?::jsonb", [json_encode([$search])]
                    ))
                    ->visibleFrom('lg'),

                /* --------------------------- fora da grelha do CRM, opcionais */

                TextColumn::make('title')
                    ->label('Título')
                    ->state(fn (Property $record) => $record->title)
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("LOWER(translations->'pt'->>'title') LIKE ?", ['%'.mb_strtolower($search).'%'])),

                TextColumn::make('business_type')
                    ->label('Finalidade')
                    ->badge()
                    ->formatStateUsing(fn (BusinessType $state) => $state->label())
                    ->color(fn (BusinessType $state) => $state->routeName() === 'buy' ? 'primary' : 'info')
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label('Publicado')
                    ->afterStateUpdated(fn () => PropertyCache::flush())
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_featured')
                    ->label('Destaque')
                    ->afterStateUpdated(fn () => PropertyCache::flush())
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_exclusive')
                    ->label('Exclusivo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('business_type')
                    ->label('Finalidade')
                    ->options(BusinessType::options()),
                SelectFilter::make('property_type')
                    ->label('Tipo')
                    ->options(fn () => Property::query()->whereNotNull('property_type')->distinct()->orderBy('property_type')->pluck('property_type', 'property_type')->all()),
                SelectFilter::make('city')
                    ->label('Concelho')
                    ->options(fn () => Property::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all()),
                TernaryFilter::make('is_active')
                    ->label('Publicado'),
                TernaryFilter::make('is_featured')
                    ->label('Em destaque'),
                // Sem este filtro, a reciclagem ficava invisível: o Filament
                // esconde o que está apagado por omissão.
                TrashedFilter::make()
                    ->label('Reciclagem'),
                TernaryFilter::make('keys')
                    ->label('Com chaves')
                    ->queries(
                        true: fn ($query) => $query->whereRaw("(admin->'keys'->>'has')::boolean is true"),
                        false: fn ($query) => $query->whereRaw("COALESCE((admin->'keys'->>'has')::boolean, false) is false"),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                // Repor e apagar de vez só aparecem no que está na reciclagem.
                RestoreAction::make()
                    ->label('Repor')
                    ->after(fn () => PropertyCache::flush()),
                ForceDeleteAction::make()
                    ->label('Apagar de vez')
                    ->after(fn () => PropertyCache::flush()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => PropertyCache::flush()),
                    RestoreBulkAction::make()
                        ->after(fn () => PropertyCache::flush()),
                    ForceDeleteBulkAction::make()
                        ->after(fn () => PropertyCache::flush()),
                ]),
            ]);
    }
}
