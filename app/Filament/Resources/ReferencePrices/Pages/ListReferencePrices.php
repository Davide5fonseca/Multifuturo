<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferencePrices extends ListRecords
{
    protected static string $resource = ReferencePriceResource::class;

    public function getSubheading(): ?string
    {
        return 'Base do simulador "Quanto vale a minha casa?" do site. Num concelho sem valor aqui, '
            .'o site usa a mediana das nossas vendas publicadas nesse concelho (mínimo 3); sem nenhuma das duas, não estima.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo valor'),
        ];
    }
}
