<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

/**
 * Detalhe de um pedido do site — só leitura: os dados vêm do formulário público
 * e não devem ser editados (são o registo do que a pessoa realmente enviou).
 */
class LeadForm
{
    /** Campos do pedido de avaliação, com os nomes que o site mostra. */
    private const PAYLOAD_LABELS = [
        'address' => 'Morada',
        'city' => 'Concelho',
        'property_type' => 'Tipo de imóvel',
        'bedrooms' => 'Tipologia',
        'area' => 'Área (m²)',
        'condition' => 'Estado de conservação',
        'estimate' => 'Estimativa mostrada no site',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contacto')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')->label('Nome')->disabled(),
                        TextInput::make('email')->label('Email')->disabled(),
                        TextInput::make('phone')->label('Telefone')->disabled(),
                    ]),
                Section::make('Pedido')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('source')
                            ->label('Origem')
                            ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                                'property' => 'Ficha de imóvel',
                                'valuation' => 'Avaliação (Quanto vale a minha casa?)',
                                default => 'Contacto geral',
                            })
                            ->disabled(),
                        TextInput::make('imovel')
                            ->label('Imóvel')
                            ->placeholder('Pedido sem imóvel associado')
                            // A referência vive numa relação: o formulário não
                            // a resolve sozinho, tem de ser lida do registo.
                            ->formatStateUsing(fn ($record) => $record?->property?->reference)
                            ->dehydrated(false)
                            ->disabled(),
                        Textarea::make('message')
                            ->label('Mensagem')
                            ->rows(5)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('payload')
                            ->label('Dados do imóvel a avaliar')
                            ->formatStateUsing(fn ($state) => is_array($state)
                                ? collect($state)->filter(fn ($v) => filled($v))->map(fn ($v, $k) => (self::PAYLOAD_LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k))).': '.$v)->implode("\n")
                                : (string) $state)
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record?->payload)),
                    ]),
                Section::make('Respostas enviadas')
                    ->columnSpanFull()
                    ->visible(fn ($record) => filled($record?->replies))
                    ->components([
                        Textarea::make('replies_texto')
                            ->hiddenLabel()
                            ->rows(8)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('RGPD')
                    ->columns(4)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('consent_contact')
                            ->label('Consentiu contacto')
                            ->formatStateUsing(fn ($state) => $state ? 'Sim' : 'Não')
                            ->disabled(),
                        TextInput::make('consent_marketing')
                            ->label('Consentiu comunicações')
                            ->formatStateUsing(fn ($state) => $state ? 'Sim' : 'Não')
                            ->disabled(),
                        TextInput::make('policy_version')
                            ->label('Versão da política aceite')
                            ->disabled(),
                        TextInput::make('created_at')
                            ->label('Recebido em')
                            // O formulário entrega a data em texto e em UTC, não
                            // como objeto — daí o parse, e a reposição do fuso da
                            // aplicação: sem ela mostrava-se menos uma hora no
                            // horário de verão.
                            ->formatStateUsing(fn ($state) => filled($state)
                                ? Carbon::parse($state)->timezone(config('app.timezone'))->translatedFormat('d/m/Y H:i')
                                : null)
                            ->disabled(),
                    ]),
            ]);
    }
}
