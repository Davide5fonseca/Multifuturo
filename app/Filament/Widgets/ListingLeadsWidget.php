<?php

namespace App\Filament\Widgets;

use App\Enums\LeadKind;
use App\Enums\LeadStage;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Leads de angariação" — quem quer vender ou arrendar connosco.
 * Mostra as que ainda estão em aberto, como o quadro do CRM.
 */
class ListingLeadsWidget extends TableWidget
{
    protected static ?string $heading = 'Leads de angariação';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            // Quem deixa o painel aberto vê chegar as novidades sem carregar em nada.
            ->poll('30s')
            ->query(
                Lead::query()
                    ->with(['assignee', 'contact', 'property'])
                    ->where('kind', LeadKind::Listing)
                    ->whereNotIn('status', [LeadStage::Listed->value, LeadStage::Lost->value])
                    ->latest()
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sem leads de angariação em aberto')
            ->emptyStateIcon('heroicon-o-inbox')
            ->columns(self::pipelineColumns())
            ->recordUrl(fn (Lead $record) => route('filament.admin.resources.leads.edit', $record));
    }

    /**
     * Colunas partilhadas com o quadro de compradores: utilizador, data,
     * propriedade, cliente, estado e prioridade — como no CRM.
     *
     * @return array<int, TextColumn>
     */
    public static function pipelineColumns(): array
    {
        return [
            TextColumn::make('assignee.name')
                ->label('Utilizador')
                ->placeholder('—')
                ->formatStateUsing(fn (?string $state) => $state ? collect(explode(' ', $state))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') : '—')
                ->tooltip(fn (Lead $record) => $record->assignee?->name)
                ->badge()
                ->color('gray'),
            TextColumn::make('created_at')
                ->label('Data')
                ->dateTime('d/m/y H:i')
                ->sortable(),
            TextColumn::make('contact_or_name')
                ->label('Cliente')
                ->state(fn (Lead $record) => str($record->contact?->name ?? $record->name)->replace(' [DEMO]', '')->toString())
                ->description(fn (Lead $record) => $record->property?->reference)
                ->wrap(),
            TextColumn::make('status')
                ->label('Estado')
                ->formatStateUsing(fn ($state) => $state?->label() ?? '—'),
            TextColumn::make('priority')
                ->label('Prioridade')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->label() ?? '—')
                ->color(fn ($state) => $state?->color() ?? 'gray'),
        ];
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('todas')
                ->label('Ver todas')
                ->url(route('filament.admin.resources.leads.index'))
                ->link(),
        ];
    }
}
