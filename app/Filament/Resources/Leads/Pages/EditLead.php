<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Detalhe de um pedido — só leitura (o formulário está todo disabled).
 * Sem botão "Guardar": a única ação é apagar (spam/duplicados).
 */
class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected static ?string $title = 'Pedido';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
