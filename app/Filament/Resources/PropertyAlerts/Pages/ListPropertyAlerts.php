<?php

namespace App\Filament\Resources\PropertyAlerts\Pages;

use App\Filament\Resources\PropertyAlerts\PropertyAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListPropertyAlerts extends ListRecords
{
    protected static string $resource = PropertyAlertResource::class;

    public function getSubheading(): ?string
    {
        return 'Pedidos "avise-me quando entrar um imóvel assim" feitos nas listagens do site. '
            .'Só recebem os confirmados por email; os envios saem de hora a hora com os imóveis publicados desde o último. '
            .'Cada email leva a ligação para cancelar; apagar aqui é para pedidos diretos da pessoa.';
    }
}
