<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('type')
                    ->options(EventType::class)
                    ->default('call')
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at'),
                Toggle::make('is_done')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Select::make('contact_id')
                    ->relationship('contact', 'name'),
                Select::make('property_id')
                    ->relationship('property', 'id'),
            ]);
    }
}
