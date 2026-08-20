<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Models\Property;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulário de imóvel do backoffice — o que a equipa preenche (substitui o CRM).
 *
 * Campos técnicos (internal_id, slug, payload_hash) não aparecem: são gerados
 * automaticamente em CreateProperty/EditProperty. A estrutura das secções será
 * afinada quando o cliente enviar os prints dos campos que quer.
 */
class PropertyForm
{
    /**
     * Junta as opções sugeridas aos valores que já existem na base de dados
     * (importações do antigo CRM podem trazer variantes: "usado", "T3", "B-").
     * Sem isto, editar um imóvel importado falhava com "valor inválido".
     *
     * @param  array<int, string>  $suggested
     * @return array<string, string>
     */
    private static function optionsWithExisting(string $column, array $suggested): array
    {
        $existing = Property::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        $all = array_values(array_unique([...$suggested, ...$existing]));

        return array_combine($all, $all);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(3)
                    ->components([
                        TextInput::make('reference')
                            ->label('Referência')
                            ->placeholder('MF-2051')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        Select::make('business_type')
                            ->label('Finalidade')
                            ->options(['sale' => 'Venda', 'rent' => 'Arrendamento'])
                            ->required()
                            ->native(false),
                        Select::make('property_type')
                            ->label('Tipo de imóvel')
                            ->options(fn () => self::optionsWithExisting('property_type', ['Apartamento', 'Moradia', 'Terreno', 'Loja', 'Escritório', 'Armazém', 'Quinta', 'Prédio', 'Garagem']))
                            ->searchable()
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Conteúdo')
                    ->components([
                        TextInput::make('translations.pt.title')
                            ->label('Título do anúncio')
                            ->placeholder('Apartamento T3 com terraço e vista de mar')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('translations.pt.description')
                            ->label('Descrição')
                            ->rows(8)
                            ->helperText('Parágrafos separados por uma linha em branco.')
                            ->columnSpanFull(),
                        FileUpload::make('photos')
                            ->label('Fotografias')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->appendFiles()
                            ->disk('public')
                            ->directory('imoveis')
                            ->maxSize(8192)
                            ->imageEditor()
                            ->helperText('A primeira fotografia é a capa. Arraste para reordenar.')
                            ->columnSpanFull(),
                        TagsInput::make('features')
                            ->label('Características')
                            ->placeholder('garagem, elevador, terraço…')
                            ->suggestions(['garagem', 'elevador', 'terraço', 'varanda', 'piscina', 'jardim', 'ar condicionado', 'lareira', 'arrecadação', 'vista de mar', 'mobilado', 'cozinha equipada', 'painéis solares', 'alarme', 'condomínio fechado'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Preço e áreas')
                    ->columns(3)
                    ->components([
                        TextInput::make('price')
                            ->label('Preço')
                            ->numeric()
                            ->prefix('€')
                            ->helperText('Vazio = "Preço sob consulta". Arrendamento: valor mensal.'),
                        TextInput::make('house_area')
                            ->label('Área útil (m²)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('gross_area')
                            ->label('Área bruta (m²)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('plot_area')
                            ->label('Área do lote (m²)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('bedrooms')
                            ->label('Tipologia (n.º de quartos)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20)
                            ->helperText('3 = T3. Vazio para terrenos/lojas.'),
                        TextInput::make('bathrooms')
                            ->label('Casas de banho')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20),
                    ]),

                Section::make('Localização')
                    ->columns(3)
                    ->components([
                        TextInput::make('district')
                            ->label('Distrito')
                            ->maxLength(96),
                        TextInput::make('city')
                            ->label('Concelho')
                            ->required()
                            ->maxLength(96),
                        TextInput::make('locality')
                            ->label('Freguesia')
                            ->maxLength(96),
                        TextInput::make('zone')
                            ->label('Zona / urbanização')
                            ->maxLength(96),
                        TextInput::make('zipcode')
                            ->label('Código postal')
                            ->placeholder('0000-000')
                            ->maxLength(16),
                        Toggle::make('gmap_visible')
                            ->label('Mostrar localização no mapa')
                            ->helperText('Desligar quando o proprietário não autoriza a morada exata.')
                            ->inline(false),
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step('0.0000001'),
                        TextInput::make('lon')
                            ->label('Longitude')
                            ->numeric()
                            ->step('0.0000001'),
                    ]),

                Section::make('Edifício')
                    ->columns(3)
                    ->components([
                        Select::make('energy_rating')
                            ->label('Certificado energético')
                            ->options(fn () => self::optionsWithExisting('energy_rating', ['A+', 'A', 'B', 'B-', 'C', 'D', 'E', 'F', 'Isento']))
                            ->required()
                            ->native(false)
                            ->helperText('Obrigatório por lei na publicitação.'),
                        Select::make('property_condition')
                            ->label('Estado')
                            ->options(fn () => self::optionsWithExisting('property_condition', ['Novo', 'Como novo', 'Renovado', 'Usado', 'Para recuperar', 'Em construção']))
                            ->native(false),
                        TextInput::make('build_year')
                            ->label('Ano de construção')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue((int) date('Y') + 5),
                        TextInput::make('floor_number')
                            ->label('Piso')
                            ->numeric(),
                    ]),

                Section::make('Ligações externas')
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        TextInput::make('video_url')
                            ->label('Vídeo (URL)')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('virtual_tour_url')
                            ->label('Visita virtual (URL)')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('floorplan_url')
                            ->label('Planta (URL)')
                            ->url()
                            ->maxLength(2048),
                    ]),

                Section::make('Publicação')
                    ->columns(3)
                    ->components([
                        Toggle::make('is_active')
                            ->label('Publicado no site')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('is_featured')
                            ->label('Em destaque na homepage')
                            ->inline(false),
                        Toggle::make('is_exclusive')
                            ->label('Exclusivo')
                            ->inline(false),
                        Select::make('broker.name')
                            ->label('Consultor')
                            ->options(fn () => Property::query()
                                ->whereNotNull('broker')
                                ->get()
                                ->pluck('broker.name')
                                ->filter()
                                ->unique()
                                ->sort()
                                ->mapWithKeys(fn ($n) => [$n => $n])
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->helperText('Escolher da lista ou escrever um nome novo no campo de pesquisa.')
                            ->createOptionForm(null),
                        DatePicker::make('crm_updated_at')
                            ->label('Data do anúncio')
                            ->default(now())
                            ->helperText('Usada na ordenação "mais recentes".'),
                    ]),
            ]);
    }
}
