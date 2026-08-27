<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferencePrice extends CreateRecord
{
    protected static string $resource = ReferencePriceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
