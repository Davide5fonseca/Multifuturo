<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Conta')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(191)
                        ->helperText('É com este endereço que entra no backoffice.'),

                    TextInput::make('password')
                        ->label('Palavra-passe')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        // Obrigatória ao criar; ao editar, em branco significa
                        // "manter a que está" (ver EditUser).
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'create'
                            ? 'Mínimo 8 caracteres. Diga-a à pessoa por um canal seguro — ela pode mudá-la no seu perfil.'
                            : 'Deixe em branco para manter a palavra-passe atual.')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->columnSpanFull(),
                ]),

            Section::make('Permissões')
                ->components([
                    Toggle::make('is_admin')
                        ->label('Administrador')
                        ->inline(false)
                        // Ninguém se pode despromover a si próprio: ficava-se
                        // sem administradores e sem forma de voltar atrás.
                        ->disabled(fn (?User $record) => $record?->getKey() === Auth::id())
                        ->dehydrated()
                        ->helperText(fn (?User $record) => $record?->getKey() === Auth::id()
                            ? 'Não pode retirar o seu próprio acesso de administrador.'
                            : 'Os administradores gerem as contas da equipa. Todos os outros usam o backoffice normalmente.'),
                ]),
        ]);
    }
}
