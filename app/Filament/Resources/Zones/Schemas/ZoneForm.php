<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Conteúdo editorial das páginas de zona (/zonas/{concelho}[/{freguesia}]).
 * A página existe sempre que haja imóveis na zona — este texto só a enriquece.
 */
class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Zona')
                    ->columns(2)
                    ->components([
                        TextInput::make('city_slug')
                            ->label('Concelho (slug)')
                            ->placeholder('cascais')
                            ->required()
                            ->maxLength(96),
                        TextInput::make('locality_slug')
                            ->label('Freguesia (slug)')
                            ->placeholder('estoril — vazio para a página do concelho')
                            ->maxLength(96),
                    ]),
                Section::make('Conteúdo')
                    ->components([
                        TextInput::make('title')
                            ->label('Título editorial')
                            ->placeholder('Viver em Cascais')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        TextInput::make('meta_description')
                            ->label('Descrição para motores de busca')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Textarea::make('intro')
                            ->label('Parágrafo de abertura')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Texto')
                            ->rows(8)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(true),
                    ]),
            ]);
    }
}
