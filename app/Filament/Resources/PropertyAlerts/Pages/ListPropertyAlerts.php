<?php

namespace App\Filament\Resources\PropertyAlerts\Pages;

use App\Filament\Resources\PropertyAlerts\PropertyAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListPropertyAlerts extends ListRecords
{
    protected static string $resource = PropertyAlertResource::class;

    protected static ?string $title = 'Alertas de imóveis';
}
