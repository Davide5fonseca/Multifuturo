<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactKind;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(191),
                    Select::make('kind')
                        ->label('Tipo')
                        ->options(ContactKind::class)
                        ->default('buyer')
                        ->required()
                        ->native(false),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(191),
                    TextInput::make('phone')
                        ->label('Telefone')
                        ->tel()
                        ->maxLength(32),
                    TextInput::make('city')
                        ->label('Concelho')
                        ->maxLength(96),
                    Select::make('assigned_to')
                        ->label('Responsável')
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false),
                ]),

            Section::make('Preferências')
                ->description('O que este cliente procura — usado para cruzar com a carteira.')
                ->columns(2)
                ->components([
                    TagsInput::make('preferences.zones')
                        ->label('Zonas de interesse')
                        ->placeholder('Escreva e prima Enter'),
                    TagsInput::make('preferences.types')
                        ->label('Tipos de imóvel')
                        ->placeholder('Escreva e prima Enter'),
                    TextInput::make('preferences.budget_min')
                        ->label('Orçamento mínimo')
                        ->numeric()
                        ->prefix('€'),
                    TextInput::make('preferences.budget_max')
                        ->label('Orçamento máximo')
                        ->numeric()
                        ->prefix('€'),
                ]),

            Section::make('Notas')
                ->components([
                    Textarea::make('notes')
                        ->hiddenLabel()
                        ->rows(5)
                        ->placeholder('Uso interno — nunca aparece no site.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
