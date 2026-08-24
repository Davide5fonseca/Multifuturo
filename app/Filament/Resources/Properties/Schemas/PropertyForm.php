<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\Geocoder;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Registo de imóvel — organizado como o CRM que substitui:
 * Geral · Interna · Localização · Media · Detalhes.
 *
 * Os campos do separador "Interna" são de gestão da agência e NÃO aparecem no
 * site; vivem na coluna jsonb `admin` (ver migration). Os campos técnicos
 * (internal_id, slug, payload_hash) são gerados automaticamente.
 */
class PropertyForm
{
    /** Classes energéticas oficiais (SCE). */
    private const ENERGY_CLASSES = ['A+', 'A', 'B', 'B-', 'C', 'D', 'E', 'F', 'Isento'];

    /* As três listas seguintes são as do CRM da CASAFARI, pela mesma ordem, para
       a equipa não ter de reaprender nada ao mudar de sistema. */

    /** Estado de conservação. */
    private const CONDITIONS = ['Em construção', 'Não aplicável', 'Novo', 'Projecto', 'Ruína', 'Usado'];

    /** Tipo de propriedade. */
    private const PROPERTY_TYPES = [
        'Apartamento', 'Casa de campo', 'Chalet', 'Empreendimento', 'Loja / comércio',
        'Moradia', 'Moradia em Banda', 'Penthouse', 'Prédio', 'Quinta', 'Ruína',
        'Terreno', 'Terreno rústico', 'Terreno urbano',
    ];

    /** Tipologia (T0 a T10, como no CRM). */
    private const TYPOLOGIES = [
        'Não aplicável', 'T0', 'T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10',
    ];

    /** Categorias de documentos (separador Media › Documentos). */
    private const DOCUMENT_CATEGORIES = [
        'Caderneta predial', 'Certidão permanente', 'Certificado energético',
        'Licença de utilização', 'Planta', 'Contrato', 'Identificação', 'Outro',
    ];

    /** Estado interno da angariação ("Actual" no CRM). Não publica nem retira do site. */
    private const STATUSES = ['Ativa', 'Inativa'];

    /**
     * Junta as opções sugeridas aos valores que já existem na base de dados
     * (importações do antigo CRM podem trazer variantes: "usado", "B-").
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

    /** Valores já usados numa coluna, para sugerir sem impedir novos. @return array<int, string> */
    private static function existingValues(string $column): array
    {
        return Property::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /** @param  array<int, string>  $values */
    private static function list(array $values): array
    {
        return array_combine($values, $values);
    }

    /** Referência seguinte da série MF-####, para a engrenagem do campo Referência. */
    private static function nextReference(): string
    {
        $last = Property::query()
            ->where('reference', 'like', 'MF-%')
            ->orderByRaw("NULLIF(regexp_replace(reference, '[^0-9]', '', 'g'), '')::bigint desc nulls last")
            ->value('reference');

        $number = $last ? ((int) preg_replace('/[^0-9]/', '', $last)) + 1 : 1;

        return 'MF-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Registo')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    self::geral(),
                    self::interna(),
                    self::localizacao(),
                    self::media(),
                    self::detalhes(),
                ]),
        ]);
    }

    /* ---------------------------------------------------------------- Geral */

    private static function geral(): Tab
    {
        return Tab::make('Geral')->schema([
            Section::make('Estado')
                ->columns(12)
                ->components([
                    Select::make('admin.status')
                        ->label('Actual')
                        ->options(self::list(self::STATUSES))
                        ->default(Property::STATUS_ACTIVE)
                        ->native(false)
                        ->live()
                        // "Inativa" retira do site: desliga logo o "Visível no
                        // website", para não ficar a dizer uma coisa e valer outra.
                        ->afterStateUpdated(function (?string $state, callable $set) {
                            if ($state === Property::STATUS_INACTIVE) {
                                $set('is_active', false);
                            }
                        })
                        ->helperText('"Inativa" retira a ficha do site.')
                        ->columnSpan(['default' => 12, 'md' => 2]),
                    TextInput::make('status_reason')
                        ->label('Motivo')
                        ->maxLength(191)
                        ->columnSpan(['default' => 12, 'md' => 7]),
                    Checkbox::make('is_sold')
                        ->label('Vendida')
                        ->helperText('Sai das listagens públicas.')
                        ->columnSpan(['default' => 12, 'md' => 3]),
                ]),

            Section::make('Geral')
                ->columns(4)
                ->components([
                    TextInput::make('reference')
                        ->label('Referência')
                        ->placeholder('MF-2051')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        // A engrenagem do CRM: sugere a referência seguinte da série.
                        ->suffixAction(
                            Action::make('gerarReferencia')
                                ->label('Gerar a referência seguinte')
                                ->icon('heroicon-m-cog-6-tooth')
                                ->action(fn (callable $set) => $set('reference', self::nextReference())),
                        ),
                    TextInput::make('internal_name')
                        ->label('Nome interno')
                        ->maxLength(191)
                        ->helperText('Nunca aparece no site.'),
                    Select::make('property_condition')
                        ->label('Conservação')
                        ->options(fn () => self::optionsWithExisting('property_condition', self::CONDITIONS))
                        ->native(false),
                    Select::make('business_type')
                        ->label('Tipo negócio')
                        ->options(BusinessType::options())
                        ->default(BusinessType::Sale->value)
                        ->required()
                        ->native(false)
                        ->helperText('Trespasse e permuta aparecem em Comprar; "arrendamento / venda" aparece nas duas listagens.'),
                    Select::make('property_type')
                        ->label('Tipo de propriedade')
                        ->options(fn () => self::optionsWithExisting('property_type', self::PROPERTY_TYPES))
                        ->searchable()
                        ->required()
                        ->native(false),
                    Select::make('typology')
                        ->label('Tipologia')
                        ->options(fn () => self::optionsWithExisting('typology', self::TYPOLOGIES))
                        ->searchable()
                        ->native(false),
                    TextInput::make('bedrooms')
                        ->label('Quarto(s)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->placeholder('0'),
                    TextInput::make('bathrooms')
                        ->label('Casas de banho')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->placeholder('0'),
                    TextInput::make('house_area')
                        ->label('Área útil (m2)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('0,00'),
                    TextInput::make('plot_area')
                        ->label('Área terreno (m2)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('0,00'),
                    TextInput::make('gross_area')
                        ->label('Área bruta (m2)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('0,00'),
                ]),

            Section::make('Preço')
                ->columns(12)
                ->components([
                    TextInput::make('price')
                        ->label('Preço')
                        ->numeric()
                        ->placeholder('0')
                        ->helperText('Arrendamento: valor mensal.')
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    Select::make('currency')
                        ->label('Moeda')
                        ->options(['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'GBP' => 'GBP (£)'])
                        ->default('EUR')
                        ->required()
                        ->native(false)
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    Checkbox::make('price_visible')
                        ->label('Preço visível')
                        ->default(true)
                        ->helperText('Desligado: o site mostra "Preço sob consulta".')
                        ->columnSpan(['default' => 12, 'md' => 4]),

                    TextInput::make('building_name')
                        ->label('Prédio \ Empreendimento')
                        ->maxLength(191)
                        // Sugere os empreendimentos já registados, sem impedir um novo.
                        ->datalist(fn () => Property::query()
                            ->whereNotNull('building_name')
                            ->distinct()
                            ->orderBy('building_name')
                            ->pluck('building_name')
                            ->all())
                        ->columnSpan(['default' => 12, 'md' => 6]),
                    TextInput::make('floor_number')
                        ->label('Nº andar')
                        ->numeric()
                        ->columnSpan(['default' => 12, 'md' => 6]),

                    Grid::make(['default' => 1, 'sm' => 12])
                        ->schema([
                            Checkbox::make('admin.sign.placed')->label('Placa')->inline(false)->columnSpan(1),
                            DatePicker::make('admin.sign.date')
                                ->hiddenLabel()
                                ->displayFormat('d/m/Y')
                                ->placeholder('DD/MM/YYYY')
                                ->columnSpan(['default' => 1, 'sm' => 4]),
                            TextInput::make('admin.sign.notes')
                                ->hiddenLabel()
                                ->placeholder('Notas')
                                ->maxLength(255)
                                ->columnSpan(['default' => 1, 'sm' => 7]),
                        ])
                        ->columnSpanFull(),

                    Checkbox::make('is_exclusive')
                        ->label('Exclusiva')
                        ->columnSpanFull(),
                    Checkbox::make('off_market')
                        ->label('Propriedade fora de mercado')
                        ->helperText('Angariação suspensa; também sai do site.')
                        ->columnSpanFull(),
                ]),

            Section::make('Visibilidade e destaques')
                ->columns(1)
                ->components([
                    Checkbox::make('is_active')
                        ->label('Visível no website')
                        ->default(true)
                        ->helperText(fn (callable $get) => $get('admin.status') === Property::STATUS_INACTIVE
                            ? 'A ficha está "Inativa" no separador Estado — não aparece no site, mesmo com isto ligado.'
                            : 'Publica ou retira a ficha do site.'),
                    Checkbox::make('is_featured')
                        ->label('Destaque'),
                    TagsInput::make('admin.monitors')
                        ->label('Monitores')
                        ->placeholder('Escreva e prima Enter')
                        ->helperText('Herdado do CRM. Fica registado na ficha, mas não há montras ligadas ao sistema.'),
                ]),

            Section::make('Angariação')
                ->columns(3)
                ->components([
                    Select::make('broker.name')
                        ->label('Angariador')
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
                        ->native(false),
                    DatePicker::make('crm_updated_at')
                        ->label('Data do anúncio')
                        ->displayFormat('d/m/Y')
                        ->default(now())
                        ->helperText('Usada na ordenação "mais recentes" do site.'),
                ]),
        ]);
    }

    private static function interna(): Tab
    {
        return Tab::make('Interna')
            ->badge('privado')
            ->schema([
                Section::make('Contrato e chaves')
                    ->columns(4)
                    ->components([
                        TextInput::make('admin.contract.number')->label('Número contrato')->maxLength(64),
                        DatePicker::make('admin.contract.start')->label('Data início')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY'),
                        DatePicker::make('admin.contract.end')->label('Data fim')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY'),
                        Checkbox::make('admin.contract.auto_renew')->label('Renova automaticamente'),

                        Grid::make(['default' => 1, 'sm' => 12])
                            ->schema([
                                Checkbox::make('admin.keys.has')->label('Chaves')->inline(false)->columnSpan(1),
                                TextInput::make('admin.keys.notes')
                                    ->hiddenLabel()
                                    ->placeholder('Notas')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'sm' => 11]),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Certificado energético')
                    ->columns(8)
                    ->components([
                        FusedGroup::make([
                            TextInput::make('admin.energy.number')->hiddenLabel()->maxLength(64)->columnSpan(3),
                            Select::make('energy_rating')
                                ->hiddenLabel()
                                ->options(fn () => self::optionsWithExisting('energy_rating', self::ENERGY_CLASSES))
                                ->required()
                                ->native(false)
                                ->placeholder('-'),
                        ])
                            ->label('Certificado energético')
                            ->columns(4)
                            ->columnSpan(4)
                            ->helperText('A classe energética é obrigatória na publicitação (Decreto-Lei n.º 118/2013).'),
                        DatePicker::make('admin.energy.valid_from')->label('Data início')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY')->columnSpan(2),
                        DatePicker::make('admin.energy.valid_to')->label('Data fim')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY')->columnSpan(2),

                        TextInput::make('admin.energy.exemption')->label('Nível de isenção')->maxLength(64)->columnSpan(4),
                        TextInput::make('admin.energy.consumption')->label('Consumo (KW h/m² ano)')->numeric()->columnSpan(4),

                        FusedGroup::make([
                            TextInput::make('admin.energy.emissions_level')->hiddenLabel()->maxLength(64)->columnSpan(3),
                            Select::make('admin.energy.emissions_class')
                                ->hiddenLabel()
                                ->options(self::list(self::ENERGY_CLASSES))
                                ->native(false)
                                ->placeholder('-'),
                        ])
                            ->label('Nível de emissões')
                            ->columns(4)
                            ->columnSpan(4),
                        TextInput::make('admin.energy.emissions')->label('Emissões (Kg CO2 / m² ano)')->numeric()->columnSpan(4),
                    ]),

                Section::make('Finanças')
                    ->columns(12)
                    ->components([
                        TextInput::make('admin.tax.matrix_number')->label('Nº de inscrição na matriz')->maxLength(64)->columnSpan(3),
                        TextInput::make('admin.tax.matrix_year')->label('Ano de inscrição')->numeric()->minValue(1800)->maxValue((int) date('Y'))->columnSpan(3),
                        TextInput::make('admin.tax.fraction')->label('Fração')->maxLength(32)->columnSpan(2),
                        FusedGroup::make([
                            TextInput::make('admin.tax.office_code')->hiddenLabel()->maxLength(16)->columnSpan(2),
                            TextInput::make('admin.tax.office')->hiddenLabel()->maxLength(96)->columnSpan(3),
                        ])
                            ->label('Código\Repartição')
                            ->columns(5)
                            ->columnSpan(4),
                    ]),

                Section::make('Conservatória')
                    ->columns(4)
                    ->components([
                        TextInput::make('admin.registry.number')->label('Número')->maxLength(64),
                        TextInput::make('admin.registry.name')->label('Nome')->maxLength(191)->columnSpan(3),
                    ]),

                Section::make('Licença de utilização')
                    ->columns(4)
                    ->components([
                        TextInput::make('admin.use_licence.number')->label('Número')->maxLength(64),
                        DatePicker::make('admin.use_licence.date')->label('Data')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY'),
                        TextInput::make('admin.use_licence.issuer')->label('Emissor')->maxLength(191)->columnSpan(2),
                    ]),

                Section::make('Licença de construção')
                    ->columns(4)
                    ->components([
                        TextInput::make('admin.build_licence.number')->label('Número')->maxLength(64),
                        DatePicker::make('admin.build_licence.date')->label('Data')->displayFormat('d/m/Y')->placeholder('DD/MM/YYYY'),
                        TextInput::make('admin.build_licence.issuer')->label('Emissor')->maxLength(191)->columnSpan(2),
                    ]),

                Section::make('Comissão')
                    ->columns(12)
                    ->components([
                        FusedGroup::make([
                            TextInput::make('admin.commission.percent')
                                ->hiddenLabel()
                                ->numeric()
                                ->prefix('%')
                                ->placeholder('0')
                                // A seta do CRM: calcula o valor a partir do preço do imóvel.
                                ->suffixAction(
                                    Action::make('comissaoEmEuros')
                                        ->label('Calcular a partir do preço')
                                        ->icon('heroicon-m-arrow-right')
                                        ->action(function ($state, callable $get, callable $set) {
                                            $price = (float) $get('price');
                                            $percent = (float) $state;

                                            if ($price > 0 && $percent > 0) {
                                                $set('admin.commission.amount', round($price * $percent / 100, 2));
                                            }
                                        }),
                                ),
                            TextInput::make('admin.commission.amount')->hiddenLabel()->numeric()->prefix('€')->placeholder('0,00'),
                        ])
                            ->hiddenLabel()
                            ->columns(2)
                            ->columnSpan(5),
                    ]),

                Section::make('Import / Export')
                    ->description('Herdados do CRM. Ficam registados na ficha, mas hoje não há importação nem exportação automática — os imóveis são geridos aqui.')
                    ->columns(1)
                    ->components([
                        Checkbox::make('admin.sync.block_import')->label('Bloquear importação'),
                        Checkbox::make('admin.sync.block_export')->label('Bloquear exportação'),
                    ]),

                Section::make('Encargo')
                    ->columns(12)
                    ->components([
                        Select::make('admin.charge.type')
                            ->label('Tipo')
                            ->options(self::list(['Nenhum', 'Hipoteca', 'Penhora']))
                            ->default('Nenhum')
                            ->native(false)
                            ->columnSpan(3),
                        TextInput::make('admin.charge.amount')->label('Valor Encargo')->numeric()->prefix('€')->placeholder('0,00')->columnSpan(4),
                    ]),

                Section::make('Etiquetas')
                    ->components([
                        TagsInput::make('admin.tags')
                            ->label('Etiquetas')
                            ->placeholder('Escreva e prima Enter')
                            ->helperText('Uso interno — aparecem na lista de imóveis e nunca no site.'),
                    ]),
            ]);
    }

    /* ---------------------------------------------------------- Localização */

    private static function localizacao(): Tab
    {
        return Tab::make('Localização')->schema([
            Section::make('Localização')
                ->columns(2)
                ->components([
                    Select::make('country')
                        ->label('País')
                        ->options(['PT' => 'Portugal', 'ES' => 'Espanha', 'FR' => 'França', 'BR' => 'Brasil'])
                        ->default('PT')
                        ->required()
                        ->native(false),
                    TextInput::make('district')
                        ->label('Distrito')
                        ->maxLength(96)
                        ->datalist(fn () => self::existingValues('district')),
                    TextInput::make('city')
                        ->label('Concelho')
                        ->required()
                        ->maxLength(96)
                        ->datalist(fn () => self::existingValues('city')),
                    TextInput::make('locality')
                        ->label('Freguesia')
                        ->maxLength(96)
                        ->datalist(fn () => self::existingValues('locality')),
                    TextInput::make('zone')
                        ->label('Zona')
                        ->maxLength(96)
                        ->placeholder('(Sem zona)')
                        ->datalist(fn () => self::existingValues('zone')),
                    TextInput::make('zipcode')
                        ->label('Código postal')
                        ->placeholder('0000-000')
                        ->maxLength(16),
                    TextInput::make('street_number')
                        ->label('Número')
                        ->maxLength(32),
                    Textarea::make('address')
                        ->label('Morada')
                        ->rows(3)
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('Localização no mapa')
                ->components([
                    Checkbox::make('gmap_visible')
                        ->label('Visível')
                        ->helperText('Desligado: as coordenadas nunca saem do servidor — não vão no HTML nem no JSON-LD da ficha.'),

                    Actions::make([
                        Action::make('geocodificar')
                            ->label('Pesquisar com os parâmetros de localização definidos')
                            ->icon('heroicon-m-magnifying-glass')
                            ->color('gray')
                            ->action(function (callable $get, callable $set): void {
                                $coords = Geocoder::search([
                                    'address' => $get('address'),
                                    'street_number' => $get('street_number'),
                                    'zipcode' => $get('zipcode'),
                                    'locality' => $get('locality'),
                                    'city' => $get('city'),
                                    'district' => $get('district'),
                                    'country' => $get('country'),
                                ]);

                                if (! $coords) {
                                    Notification::make()
                                        ->title('Não foi possível localizar')
                                        ->body('Verifique a morada, o concelho e o código postal — ou marque o ponto no mapa.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $set('lat', $coords['lat']);
                                $set('lon', $coords['lon']);

                                Notification::make()
                                    ->title('Localização encontrada')
                                    ->body($coords['label'])
                                    ->success()
                                    ->send();
                            }),
                    ])->fullWidth(),

                    ViewField::make('map')
                        ->hiddenLabel()
                        ->view('filament.forms.property-map')
                        ->dehydrated(false),

                    Grid::make(3)->schema([
                        TextInput::make('lat')->label('Latitude')->numeric()->step('0.0000001'),
                        TextInput::make('lon')->label('Longitude')->numeric()->step('0.0000001'),
                    ]),
                ]),
        ]);
    }

    /* ---------------------------------------------------------------- Media */

    private static function media(): Tab
    {
        return Tab::make('Media')->schema([
            Tabs::make('MediaTabs')
                ->contained(false)
                ->tabs([
                    Tab::make('Fotos')->schema([
                        FileUpload::make('photos')
                            ->hiddenLabel()
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->appendFiles()
                            ->disk('public')
                            ->directory('imoveis')
                            ->maxSize(8192)
                            ->imageEditor()
                            ->uploadingMessage('A carregar fotos…')
                            ->helperText('A primeira é a capa. Arraste para alterar a ordenação.')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Documentos')->schema([
                        Repeater::make('documents')
                            ->hiddenLabel()
                            ->addActionLabel('Carregar ficheiros')
                            ->defaultItems(0)
                            ->table([
                                Repeater\TableColumn::make('Ficheiro'),
                                Repeater\TableColumn::make('Nome'),
                                Repeater\TableColumn::make('Visível'),
                                Repeater\TableColumn::make('Categoria'),
                                Repeater\TableColumn::make('Enviar para os portais'),
                                Repeater\TableColumn::make('Disponível em resposta predefinida'),
                            ])
                            ->schema([
                                FileUpload::make('file')
                                    ->hiddenLabel()
                                    ->disk('local')                 // fora de public/: nunca servidos ao visitante
                                    ->directory('documentos')
                                    ->maxSize(20480)
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                    ->required(),
                                TextInput::make('name')->hiddenLabel()->maxLength(191)->placeholder('Nome do documento'),
                                Checkbox::make('visible')->hiddenLabel()->inline(false),
                                Select::make('category')
                                    ->hiddenLabel()
                                    ->options(self::list(self::DOCUMENT_CATEGORIES))
                                    ->native(false),
                                Checkbox::make('portals')->hiddenLabel()->inline(false),
                                Checkbox::make('predefined_reply')->hiddenLabel()->inline(false),
                            ])
                            ->helperText('Uso interno — os documentos ficam em disco privado e nunca são publicados no site. "Visível", "portais" e "resposta predefinida" são campos herdados do CRM: ficam registados, mas não há portais nem respostas predefinidas ligados ao sistema.')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Links')->schema([
                        TextInput::make('video_url')->label('Vídeo')->url()->maxLength(2048),
                        TextInput::make('virtual_tour_url')->label('Visita Virtual / 360º')->url()->maxLength(2048),
                        TextInput::make('floorplan_url')->label('Planta')->url()->maxLength(2048),
                    ]),

                    Tab::make('Visita virtual')->schema([
                        FileUpload::make('admin.photos_360')
                            ->label('Adicionar foto')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->appendFiles()
                            ->disk('public')
                            ->directory('imoveis/360')
                            ->maxSize(16384)
                            ->helperText('As fotos em 360º ficam guardadas na ficha. A criação automática da visita era um serviço do CRM; para publicar um tour feito noutra plataforma, use o campo "Visita Virtual / 360º" em Links.')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    /* ------------------------------------------------------------- Detalhes */

    /**
     * Comodidades do separador Detalhes › Geral, pela ordem do CRM. Todas vivem
     * no array público `features` (filtros do site via índice GIN); os grupos
     * existem só para o formulário reproduzir a disposição do CRM.
     *
     * @var array<string, array<string, string>>
     */
    public const DETAIL_FEATURE_GROUPS = [
        'det_features_a' => [
            'terraço' => 'Terraço', 'garagem' => 'Garagem', 'lavandaria' => 'Lavandaria',
            'condomínio fechado' => 'Condomínio fechado', 'adega' => 'Adega', 'cave' => 'Cave',
            'arrecadação' => 'Arrecadação',
        ],
        'det_views' => [
            'vista mar' => 'Vista mar', 'vista campo' => 'Vista campo', 'vista golfe' => 'Vista golfe',
            'vista montanha' => 'Vista montanha', 'vista rio' => 'Vista rio', 'vista cidade' => 'Vista cidade',
            'vista piscina' => 'Vista piscina', 'vista vila' => 'Vista vila',
            'vista urbanização' => 'Vista urbanização', 'vista praia' => 'Vista praia',
            'vista marina' => 'Vista marina', 'vista jardim' => 'Vista jardim', 'vista lago' => 'Vista lago',
        ],
        'det_features_b' => [
            'propriedade em primeira linha' => 'Propriedade em primeira linha',
            'video porteiro' => 'Video porteiro', 'alarme' => 'Alarme', 'elevador' => 'Elevador',
            'anexo para visitas' => 'Anexo para visitas', 'vidros duplos' => 'Vidros duplos',
            'estores eléctricos' => 'Estores eléctricos',
            'portão de garagem eléctrico' => 'Portão de garagem eléctrico', 'barbecue' => 'Barbecue',
            'terreno vedado' => 'Terreno vedado', 'domótica' => 'Domótica',
            'porta de segurança' => 'Porta de segurança', 'vistas panorâmicas' => 'Vistas panorâmicas',
        ],
        'det_features_c' => [
            'ginásio' => 'Ginásio', 'sistema som central' => 'Sistema som central',
            'localização central' => 'Localização central', 'aquecedor a gás' => 'Aquecedor a gás',
            'sotão' => 'Sotão', 'localização sossegada' => 'Localização sossegada',
            'marquise' => 'Marquise', 'kitchenette' => 'Kitchenette', 'cavalariças' => 'Cavalariças',
            'picadeiro' => 'Picadeiro', 'rega automática' => 'Rega automática', 'despensa' => 'Despensa',
            'forno de lenha' => 'Forno de lenha', 'cisterna' => 'Cisterna', 'furo' => 'Furo',
            'fossa' => 'Fossa', 'esgotos municipais' => 'Esgotos municipais',
            'curta distância a pé da praia' => 'Curta distância a pé da praia', 'varanda' => 'Varanda',
            'água da rede' => 'Água da rede', 'licença turística' => 'Licença turística',
        ],
        'det_features_d' => [
            'área de estacionamento' => 'Área de estacionamento', 'poço' => 'Poço',
            'aquecimento solar' => 'Aquecimento solar',
            'lareira com recuperador de calor' => 'Lareira com recuperador de calor',
            'sistema de irrigação' => 'Sistema de irrigação',
            'banheira de hidromassagem' => 'Banheira de Hidromassagem',
        ],
        'det_features_e' => [
            'com estacionamento' => 'Com estacionamento', 'mobilado' => 'Mobilado',
        ],
        'det_interior' => [
            'aquecimento' => 'Aquecimento', 'máquina lavar roupa' => 'Máquina lavar roupa',
            'máquina lavar loiça' => 'Máquina lavar loiça', 'ar condicionado' => 'Ar condicionado',
            'chão aquecido' => 'Chão aquecido', 'salamandra' => 'Salamandra', 'lareira' => 'Lareira',
            'aspiração central' => 'Aspiração central', 'roupeiros' => 'Roupeiros',
            'cozinha equipada' => 'Cozinha equipada', 'closet' => 'Closet',
            'chão radiante' => 'Chão radiante', 'aquecimento central a gás' => 'Aquecimento central a gás',
            'cofre' => 'Cofre', 'domótica pré instalação' => 'Domótica pré instalação',
            'alarme pré instalação' => 'Alarme pré instalação',
            'painéis solares pré instalação' => 'Painéis solares pré instalação',
            'chão flutuante' => 'Chão flutuante', 'termo acumulador' => 'Termo acumulador',
            'pré-instalação ar condicionado' => 'Pré-instalação ar condicionado',
        ],
        'det_exterior' => [
            'jardim' => 'Jardim', 'court de ténis' => 'Court de Ténis',
            'jacuzzi' => 'Jacuzzi', 'piscina' => 'Piscina',
        ],
        'det_proximity' => [
            'proximidade: aeroporto' => 'Aeroporto', 'proximidade: serra' => 'Serra',
            'proximidade: praia' => 'Praia', 'proximidade: campo golfe' => 'Campo golfe',
            'proximidade: zona comercial' => 'Zona comercial',
            'proximidade: parque infantil' => 'Parque infantil',
            'proximidade: restaurantes' => 'Restaurantes', 'proximidade: cidade' => 'Cidade',
            'proximidade: campo' => 'Campo', 'proximidade: hospital' => 'Hospital',
            'proximidade: farmácia' => 'Farmácia',
            'proximidade: transportes públicos' => 'Transportes Públicos',
            'proximidade: escolas' => 'Escolas', 'proximidade: piscinas públicas' => 'Piscinas Públicas',
        ],
    ];

    /**
     * Junta os grupos do formulário (e as características livres) no array
     * `features` que o site conhece, e retira os campos transitórios.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function foldDetailFeatures(array $data): array
    {
        // Um 'features' já vindo nos dados (importações, testes) mantém-se à frente.
        $features = $data['features'] ?? [];
        foreach (array_keys(self::DETAIL_FEATURE_GROUPS) as $group) {
            $features = [...$features, ...($data[$group] ?? [])];
            unset($data[$group]);
        }
        $features = [...$features, ...($data['det_features_extra'] ?? [])];
        unset($data['det_features_extra']);

        $data['features'] = array_values(array_unique($features));

        return $data;
    }

    /**
     * O inverso: divide o `features` guardado pelos grupos do formulário; o que
     * não pertencer a nenhum (importações, valores antigos) vai para as
     * características livres, para nada se perder ao gravar.
     *
     * @param  array<int, string>|null  $features
     * @return array<string, array<int, string>>
     */
    public static function splitDetailFeatures(?array $features): array
    {
        $features = $features ?? [];
        $state = [];
        foreach (self::DETAIL_FEATURE_GROUPS as $group => $options) {
            $state[$group] = array_values(array_intersect($features, array_keys($options)));
        }
        $known = array_merge(...array_map(array_keys(...), array_values(self::DETAIL_FEATURE_GROUPS)));
        $state['det_features_extra'] = array_values(array_diff($features, $known));

        return $state;
    }

    private static function detalhes(): Tab
    {
        return Tab::make('Detalhes')->schema([
            Tabs::make('DetalhesTabs')
                ->contained(false)
                ->tabs([
                    Tab::make('Geral')->schema([
                        TextInput::make('build_year')
                            ->label('Ano construção')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue((int) date('Y') + 5)
                            ->columnSpan(['default' => 12, 'md' => 3]),
                        TextInput::make('details.floors')
                            ->label('Pisos')
                            ->numeric()
                            ->minValue(0)
                            ->columnSpan(['default' => 12, 'md' => 3]),

                        CheckboxList::make('det_features_a')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_features_a'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),

                        CheckboxList::make('det_views')
                            ->label('Vista')
                            ->options(self::DETAIL_FEATURE_GROUPS['det_views'])
                            ->columns(['default' => 2, 'md' => 4])
                            ->columnSpanFull(),

                        CheckboxList::make('det_features_b')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_features_b'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),

                        CheckboxList::make('details.solar_orientation')
                            ->label('Orientação solar')
                            ->options(['Norte' => 'Norte', 'Sul' => 'Sul', 'Este' => 'Este', 'Oeste' => 'Oeste'])
                            ->columns(['default' => 2, 'md' => 4])
                            ->columnSpanFull(),

                        CheckboxList::make('det_features_c')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_features_c'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),

                        Select::make('details.orientation')
                            ->label('Orientação')
                            ->options(self::list(['Exterior', 'Interior']))
                            ->placeholder('-')
                            ->native(false)
                            ->columnSpan(['default' => 12, 'md' => 4]),
                        Select::make('details.occupancy')
                            ->label('Ocupação Atual')
                            ->options(self::list(['Ocupado', 'Livre', 'Propriedade Nua', 'Arrendado', 'Ocupação Ilegal']))
                            ->placeholder('-')
                            ->native(false)
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        CheckboxList::make('det_features_d')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_features_d'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),

                        TextInput::make('details.renovation_year')
                            ->label('Ano de renovação')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue((int) date('Y') + 5)
                            ->columnSpan(['default' => 12, 'md' => 3]),

                        CheckboxList::make('det_features_e')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_features_e'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),

                        TagsInput::make('det_features_extra')
                            ->label('Outras características')
                            ->placeholder('Escreva e prima Enter')
                            ->helperText('Livres — também aparecem no site. As importações antigas caem aqui.')
                            ->columnSpanFull(),
                    ])->columns(12),

                    Tab::make('Interior')->schema([
                        CheckboxList::make('det_interior')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_interior'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),
                    ]),
                    Tab::make('Exterior')->schema([
                        CheckboxList::make('det_exterior')
                            ->hiddenLabel()
                            ->options(self::DETAIL_FEATURE_GROUPS['det_exterior'])
                            ->columns(['default' => 1, 'md' => 3])
                            ->columnSpanFull(),
                        CheckboxList::make('det_proximity')
                            ->label('Proximidade')
                            ->options(self::DETAIL_FEATURE_GROUPS['det_proximity'])
                            ->columns(['default' => 2, 'md' => 4])
                            ->columnSpanFull(),
                    ]),
                ]),

        ]);
    }
}
