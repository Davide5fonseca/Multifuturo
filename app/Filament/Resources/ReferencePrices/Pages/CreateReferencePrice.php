<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use App\Support\Valuation;
use Filament\Resources\Pages\CreateRecord;

class CreateReferencePrice extends CreateRecord
{
    protected static string $resource = ReferencePriceResource::class;

    /** O que se grava à mão é manual: a importação do INE não lhe toca. */
    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
