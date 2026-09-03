<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Próximos eventos" e "Datas a lembrar" — a agenda por fazer: telefonemas,
 * visitas, reuniões e lembretes. O que já passou da hora aparece a vermelho.
 */
class UpcomingEventsWidget extends TableWidget
{
    protected static ?string $heading = 'Agenda — próximos eventos';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            // Quem deixa o painel aberto vê chegar as novidades sem carregar em nada.
            ->poll('60s')
            ->query(
                Event::query()
                    ->with(['contact', 'property', 'user'])
                    ->where('is_done', false)
                    ->orderBy('starts_at')
            )
            ->paginated([8, 25])
            ->defaultPaginationPageOption(8)
            ->emptyStateHeading('Agenda em dia')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->weight('medium')
                    ->description(fn (Event $record) => $record->contact?->name),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->icon(fn ($state) => $state->icon())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    // Atrasado destaca-se, como no CRM.
                    ->color(fn (Event $record) => $record->starts_at->isPast() ? 'danger' : null)
                    ->weight(fn (Event $record) => $record->starts_at->isPast() ? 'bold' : null),
                TextColumn::make('property.reference')
                    ->label('Imóvel')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Event $record) => $record->update(['is_done' => true])),
            ]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('agenda')
                ->label('Ver agenda')
                ->url(route('filament.admin.resources.events.index'))
                ->link(),
        ];
    }
}
