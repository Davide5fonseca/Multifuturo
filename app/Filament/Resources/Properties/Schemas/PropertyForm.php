<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Enums\BusinessType;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    /** Motivos do estado — por confirmar com a lista do CRM. */
    private const STATUS_REASONS = [
        'Angariação terminada',
        'Contrato terminado',
        'Em avaliação',
        'Proprietário desistiu',
        'Retirada pelo proprietário',
        'Vendida por terceiros',
        'Outro',
    ];

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
                    Select::make('status_reason')
                        ->label('Motivo')
                        ->options(fn () => self::optionsWithExisting('status_reason', self::STATUS_REASONS))
                        ->native(false)
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
                        ->columnSpan(['default' => 12, 'md' => 3]),
                    TextInput::make('build_year')
                        ->label('Ano de construção')
                        ->numeric()
                        ->minValue(1800)
                        ->maxValue((int) date('Y') + 5)
                        ->columnSpan(['default' => 12, 'md' => 3]),

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
                            ->options(self::list(['Nenhum', 'Hipoteca', 'Penhora', 'Usufruto', 'Outro']))
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
                ->columns(3)
                ->components([
                    TextInput::make('country')->label('País')->default('PT')->maxLength(2)->required(),
                    TextInput::make('district')->label('Distrito')->maxLength(96),
                    TextInput::make('city')->label('Concelho')->required()->maxLength(96),
                    TextInput::make('locality')->label('Freguesia')->maxLength(96),
                    TextInput::make('zone')->label('Zona')->maxLength(96),
                    TextInput::make('zipcode')->label('Código postal')->placeholder('0000-000')->maxLength(16),
                    TextInput::make('address')->label('Morada')->maxLength(255)->columnSpan(2),
                    TextInput::make('street_number')->label('Número')->maxLength(32),
                ]),

            Section::make('Localização no mapa')
                ->columns(3)
                ->components([
                    Toggle::make('gmap_visible')
                        ->label('Mostrar o mapa no site')
                        ->inline(false)
                        ->helperText('Desligado: as coordenadas nunca saem do servidor (compromisso com o proprietário).'),
                    TextInput::make('lat')->label('Latitude')->numeric()->step('0.0000001'),
                    TextInput::make('lon')->label('Longitude')->numeric()->step('0.0000001'),
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

    private static function detalhes(): Tab
    {
        return Tab::make('Detalhes')->schema([
            Section::make('Anúncio')
                ->components([
                    TextInput::make('translations.pt.title')
                        ->label('Título do anúncio')
                        ->placeholder('Apartamento T3 com terraço e vista de mar')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('translations.pt.description')
                        ->label('Descrição')
                        ->rows(10)
                        ->helperText('Parágrafos separados por uma linha em branco.')
                        ->columnSpanFull(),
                ]),

            Section::make('Características')
                ->components([
                    TagsInput::make('features')
                        ->label('Características e comodidades')
                        ->placeholder('garagem, elevador, terraço…')
                        ->suggestions(['garagem', 'elevador', 'terraço', 'varanda', 'piscina', 'jardim', 'ar condicionado', 'lareira', 'arrecadação', 'vista de mar', 'mobilado', 'cozinha equipada', 'painéis solares', 'alarme', 'condomínio fechado', 'churrasqueira', 'despensa', 'suite', 'roupeiros', 'aquecimento central'])
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
