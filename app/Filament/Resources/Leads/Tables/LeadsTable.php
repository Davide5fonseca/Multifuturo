<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                // Por responder é o que interessa de relance: quem abre a caixa
                // quer ver o que ainda está à espera de alguém.
                TextColumn::make('replied_at')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Lead $record) => $record->foiRespondida() ? 'Respondida' : 'Por responder')
                    ->color(fn (string $state) => $state === 'Respondida' ? 'success' : 'warning')
                    ->tooltip(fn (Lead $record) => $record->replied_at
                        ? 'Respondida '.$record->replied_at->diffForHumans()
                        : null)
                    ->sortable(),
                IconColumn::make('consent_contact')
                    ->label('Consent.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('respondida')
                    ->label('Respondida')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('replied_at'),
                        false: fn ($query) => $query->whereNull('replied_at'),
                        blank: fn ($query) => $query,
                    ),
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
