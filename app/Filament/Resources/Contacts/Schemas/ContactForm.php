<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('kind')
                    ->options(ContactKind::class)
                    ->default('buyer')
                    ->required(),
                TextInput::make('city'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('preferences')
                    ->required()
                    ->default('{}'),
                TextInput::make('assigned_to')
                    ->numeric(),
            ]);
    }
}
