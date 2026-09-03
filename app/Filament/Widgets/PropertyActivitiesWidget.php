<?php

namespace App\Filament\Widgets;

use App\Models\PropertyActivity;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Actualizações" — histórico do que a equipa andou a mexer nos imóveis:
 * novas fichas, alterações de preço, imóveis retirados. Alimentado
 * automaticamente pelo PropertyObserver.
 */
class PropertyActivitiesWidget extends TableWidget
{
    protected static ?string $heading = 'Actualizações';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            // Quem deixa o painel aberto vê chegar as novidades sem carregar em nada.
            ->poll('60s')
            ->query(PropertyActivity::query()->with(['property', 'user'])->latest())
            ->paginated([8, 25])
            ->defaultPaginationPageOption(8)
            ->emptyStateHeading('Ainda sem alterações registadas')
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                /*
                 * Registo de atividade, não folha de cálculo: quatro colunas em
                 * vez de seis. Em meia largura, seis colunas empurravam o detalhe
                 * para fora do cartão.
                 */
                ImageColumn::make('foto')
                    ->label('')
                    ->disk(null)
                    // As fotos carregadas no backoffice são "/storage/…": o Filament só mostra URLs completos.
                    ->getStateUsing(fn (PropertyActivity $record): ?string => $record->property?->coverPhotoUrl())
                    ->defaultImageUrl(asset('images/placeholder-property.jpg'))
                    ->height(40)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                TextColumn::make('property.reference')
                    ->label('Imóvel')
                    // Sem estado, o Filament mostra o marcador e deixa cair a descrição:
                    // o detalhe é o que interessa nas linhas de imóveis já apagados.
                    ->getStateUsing(fn (PropertyActivity $record): string => $record->property?->reference ?? '—')
                    ->weight('medium')
                    ->description(fn (PropertyActivity $record): ?string => $record->detail)
                    ->wrap()
                    ->url(fn (PropertyActivity $record) => $record->property ? route('filament.admin.resources.properties.edit', $record->property) : null),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PropertyActivity::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'price' => 'warning',
                        'status' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->since()
                    ->tooltip(fn (PropertyActivity $record): string => $record->created_at->format('d-m-Y H:i'))
                    ->description(fn (PropertyActivity $record): ?string => $record->user?->name)
                    ->sortable(),
            ]);
    }
}
