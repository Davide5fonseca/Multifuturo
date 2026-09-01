<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListReferencePrices extends ListRecords
{
    protected static string $resource = ReferencePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importarIne')
                ->label('Importar do INE')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Importar valores do INE')
                ->modalDescription('Vai buscar ao INE o valor mediano por m² de todos os concelhos e freguesias (avaliação bancária por tipo e vendas dos últimos 12 meses). Demora alguns segundos. Os valores escritos à mão não são tocados.')
                ->modalSubmitActionLabel('Importar')
                ->action(function (): void {
                    $code = Artisan::call('valuation:import-ine');
                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title($code === 0 ? 'Valores do INE importados' : 'A importação falhou')
                        ->body($output)
                        ->{$code === 0 ? 'success' : 'danger'}()
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->label('Novo valor'),
        ];
    }
}
