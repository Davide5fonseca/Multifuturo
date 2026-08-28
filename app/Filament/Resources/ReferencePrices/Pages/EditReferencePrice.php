<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use App\Support\Valuation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferencePrice extends EditRecord
{
    protected static string $resource = ReferencePriceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['scope'] = ($data['city'] ?? null) === Valuation::DEFAULT_CITY ? 'default' : 'city';

        return $data;
    }

    /** Editar um valor do INE torna-o manual: a próxima importação já não o pisa. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // "Todos os concelhos" guarda-se com o marcador DEFAULT_CITY e sem freguesia.
        if (($data['scope'] ?? 'city') === 'default') {
            $data['city'] = Valuation::DEFAULT_CITY;
            $data['locality'] = '';
        }

        unset($data['scope']);
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
