<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContactKind;
use App\Models\Contact;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ainda sem clientes')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ContactKind $state) => $state->label())
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('Concelho')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('responsavel.name')
                    ->label('Responsável')
                    ->state(fn (Contact $record) => User::find($record->assigned_to)?->name)
                    ->placeholder('—'),
                TextColumn::make('leads_count')
                    ->label('Pedidos')
                    ->counts('leads')
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(ContactKind::class),
                SelectFilter::make('assigned_to')
                    ->label('Responsável')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all()),
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
