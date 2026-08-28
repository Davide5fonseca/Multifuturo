<?php

namespace App\Filament\Resources\PropertyAlerts\Tables;

use App\Models\PropertyAlert;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PropertyAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PropertyAlert $record) => $record->name),
                TextColumn::make('listing')
                    ->label('Listagem')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'rent' ? 'Arrendar' : 'Comprar')
                    ->color(fn (string $state) => $state === 'rent' ? 'info' : 'primary'),
                TextColumn::make('criteria')
                    ->label('Critérios')
                    ->state(fn (PropertyAlert $record) => $record->summary())
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (PropertyAlert $record) => match (true) {
                        $record->unsubscribed_at !== null => 'Cancelado',
                        $record->confirmed_at !== null => 'Ativo',
                        default => 'Por confirmar',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Ativo' => 'success',
                        'Cancelado' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('sent_count')
                    ->label('Envios')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_sent_at')
                    ->label('Último envio')
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Pedido')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(['active' => 'Ativos', 'pending' => 'Por confirmar', 'unsubscribed' => 'Cancelados'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'active' => $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'),
                        'pending' => $query->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
                        'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
                        default => $query,
                    }),
                SelectFilter::make('listing')
                    ->label('Listagem')
                    ->options(['buy' => 'Comprar', 'rent' => 'Arrendar']),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
