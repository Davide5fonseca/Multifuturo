<?php

namespace App\Filament\Resources\ReferencePrices\Pages;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferencePrice extends EditRecord
{
    protected static string $resource = ReferencePriceResource::class;

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
