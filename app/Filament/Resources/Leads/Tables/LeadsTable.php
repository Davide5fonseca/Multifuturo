<?php

namespace App\Filament\Resources\Leads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Caixa de entrada: mais recentes primeiro, badges por origem. */
class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recebido')
                    ->since()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state->value) {
                        'property' => 'Imóvel',
                        'valuation' => 'Avaliação',
                        default => 'Contacto',
                    })
                    ->color(fn ($state) => match ($state->value) {
                        'property' => 'primary',
                        'valuation' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('property.reference')
                    ->label('Referência')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('consent_contact')
                    ->label('Consent.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Origem')
                    ->options(['property' => 'Imóvel', 'contact' => 'Contacto', 'valuation' => 'Avaliação']),
            ])
            ->recordActions([
                EditAction::make()->label('Ver'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
