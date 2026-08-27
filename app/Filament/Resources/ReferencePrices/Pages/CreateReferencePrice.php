<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferencePrice extends CreateRecord
{
    protected static string $resource = ReferencePriceResource::class;

    /** O que se grava à mão é manual: a importação do INE não lhe toca. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['locality'] = trim((string) ($data['locality'] ?? ''));
        $data['source'] = 'manual';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
