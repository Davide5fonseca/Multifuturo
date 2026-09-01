<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventType;
use App\Models\Property;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Evento')
                ->columns(2)
                ->components([
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(191)
                        ->columnSpanFull(),
                    Select::make('type')
                        ->label('Tipo')
                        ->options(EventType::class)
                        ->default('call')
                        ->required()
                        ->searchable()
                        ->native(false),
                    Toggle::make('is_done')
                        ->label('Concluído')
                        ->inline(false)
                        ->extraFieldWrapperAttributes(['class' => 'ao-nivel']),
                    DateTimePicker::make('starts_at')
                        ->label('Início')
                        ->seconds(false)
                        ->displayFormat('d/m/Y H:i')
                        ->default(now()->addHour()->startOfHour())
                        ->required(),
                    DateTimePicker::make('ends_at')
                        ->label('Fim')
                        ->seconds(false)
                        ->displayFormat('d/m/Y H:i')
                        ->after('starts_at'),
                ]),

            Section::make('Ligações')
                ->columns(3)
                ->components([
                    Select::make('user_id')
                        ->label('Utilizador')
                        ->relationship('user', 'name')
                        ->default(fn () => auth()->id())
                        ->searchable()
                        ->native(false),
                    Select::make('contact_id')
                        ->label('Cliente')
                        ->relationship('contact', 'name')
                        ->searchable()
                        ->native(false),
                    Select::make('property_id')
                        ->label('Imóvel')
                        ->options(fn () => Property::query()
                            ->orderBy('reference')
                            ->pluck('reference', 'id')
                            ->all())
                        ->searchable()
                        ->native(false),
                ]),

            Section::make('Notas')
                ->components([
                    Textarea::make('notes')
                        ->hiddenLabel()
                        ->rows(4)
                        ->placeholder('Uso interno — nunca aparece no site.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
