<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (User $record) => $record->getKey() !== Auth::id()),
        ];
    }

    /**
     * A palavra-passe nunca volta preenchida ao formulário — mostrá-la, mesmo
     * cifrada, não serve para nada e confunde quem edita.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = null;
        $data['modules'] = $this->record->moduleAccess()->pluck('module')->all();

        return $data;
    }

    /** Os módulos escolhidos no formulário passam para module_access. */
    protected function afterSave(): void
    {
        $this->record->syncModules((array) ($this->data['modules'] ?? []));
    }
}
