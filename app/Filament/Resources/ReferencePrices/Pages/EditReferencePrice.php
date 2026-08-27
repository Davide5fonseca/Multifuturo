<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferencePrice extends EditRecord
{
    protected static string $resource = ReferencePriceResource::class;

    /** Editar um valor do INE torna-o manual: a próxima importação já não o pisa. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['locality'] = trim((string) ($data['locality'] ?? ''));
        $data['source'] = 'manual';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
