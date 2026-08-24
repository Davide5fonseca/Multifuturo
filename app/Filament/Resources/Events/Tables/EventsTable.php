<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('Ainda sem eventos na agenda')
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Event $record) => $record->contact?->name),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (EventType $state) => $state->label())
                    ->icon(fn (EventType $state) => $state->icon())
                    ->color(fn (EventType $state) => $state->color())
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    // Atrasado destaca-se, como no CRM.
                    ->color(fn (Event $record) => ! $record->is_done && $record->starts_at->isPast() ? 'danger' : null),
                TextColumn::make('ends_at')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_done')
                    ->label('Concluído')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Utilizador')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('property.reference')
                    ->label('Imóvel')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(EventType::class),
                SelectFilter::make('user_id')
                    ->label('Utilizador')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('is_done')
                    ->label('Concluído'),
            ])
            ->recordActions([
                Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Event $record) => ! $record->is_done)
                    ->action(fn (Event $record) => $record->update(['is_done' => true])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
