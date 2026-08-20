<?php

namespace App\Filament\Widgets;

use App\Enums\LeadKind;
use App\Enums\LeadStage;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Leads de compradores" — pedidos de quem procura casa, incluindo os que
 * entram pelos formulários do site. Mostra os que ainda estão em aberto.
 */
class BuyerLeadsWidget extends TableWidget
{
    protected static ?string $heading = 'Leads de compradores';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
                    ->with(['assignee', 'contact', 'property'])
                    ->where('kind', LeadKind::Buyer)
                    ->whereNotIn('status', [LeadStage::Closed->value, LeadStage::Lost->value])
                    ->latest()
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sem pedidos em aberto')
            ->emptyStateIcon('heroicon-o-inbox')
            ->columns(ListingLeadsWidget::pipelineColumns())
            ->recordUrl(fn (Lead $record) => route('filament.admin.resources.leads.edit', $record));
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('todos')
                ->label('Ver todos')
                ->url(route('filament.admin.resources.leads.index'))
                ->link(),
        ];
    }
}
