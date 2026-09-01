<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Os módulos escolhidos no formulário passam para module_access. */
    protected function afterCreate(): void
    {
        $this->record->syncModules((array) ($this->data['modules'] ?? []));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Conta criada. Dê a palavra-passe à pessoa por um canal seguro.';
    }
}
