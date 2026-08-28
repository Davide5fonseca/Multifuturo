<?php

namespace App\Filament\Resources\ReferencePrices\Schemas;

use App\Filament\Resources\ReferencePrices\ReferencePriceResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ReferencePriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Valor por m²')
                    ->description('O simulador multiplica este valor pela área que o visitante indica. Um valor por concelho (ou freguesia) e tipo; o mesmo trio não pode repetir-se. O que se grava aqui é "manual" e a importação do INE nunca o pisa. Um valor com âmbito "Todos os concelhos" serve de rede para o que não tem valor próprio — útil para terrenos, que o INE não publica.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('scope')
                            ->label('Âmbito')
                            ->options(['city' => 'Um concelho (ou uma freguesia)', 'default' => 'Todos os concelhos sem valor próprio'])
                            ->default('city')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Concelho')
                            ->placeholder('Sintra')
                            ->helperText('Escreva o nome como está nas fichas dos imóveis.')
                            ->required(fn (Get $get) => $get('scope') !== 'default')
                            ->visible(fn (Get $get) => $get('scope') !== 'default')
                            ->maxLength(96)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                ->where('property_type', $get('property_type'))
                                ->where('locality', trim((string) $get('locality'))))
                            ->validationMessages(['unique' => 'Já existe um valor para este concelho, freguesia e tipo — edite esse.']),
                        TextInput::make('locality')
                            ->label('Freguesia')
                            ->placeholder('vazio = o concelho inteiro')
                            ->visible(fn (Get $get) => $get('scope') !== 'default')
                            ->maxLength(191),
                        Select::make('property_type')
                            ->label('Tipo')
                            ->options(ReferencePriceResource::TYPES)
                            ->default('apartment')
                            ->required()
                            ->native(false),
                        TextInput::make('price_per_m2')
                            ->label('Valor')
                            ->suffix('€/m²')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Fonte e data')
                            ->placeholder('Ex.: INE, 1.º trimestre de 2026 · média das nossas vendas de 2025')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
