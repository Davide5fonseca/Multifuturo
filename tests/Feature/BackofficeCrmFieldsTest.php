<?php

/*
 * Campos equivalentes ao antigo CRM: dados internos (jsonb `admin`), estados
 * (vendida / fora de mercado), preço visível e documentos privados.
 */

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\User;
use App\Support\Geocoder;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('guarda os dados internos (contrato, chaves, finanças, licenças, comissão) em admin', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-4001',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'typology' => 'T3',
            'city' => 'Águeda',
            'energy_rating' => 'B',
            'internal_name' => 'Casa da esquina (Sr. Silva)',
            'admin' => [
                'contract' => ['number' => 'C-2026/14', 'start' => '2026-01-10', 'auto_renew' => true],
                'keys' => ['has' => true, 'notes' => 'Chaveiro 12'],
                'energy' => ['number' => 'SCE123456', 'consumption' => 120, 'emissions_level' => 'B2', 'emissions_class' => 'B'],
                'tax' => ['matrix_number' => '4567', 'fraction' => 'B', 'office_code' => '19', 'office' => 'AGUEDA'],
                'sync' => ['block_import' => true, 'block_export' => false],
                'use_licence' => ['number' => '31/2019', 'issuer' => 'CM Águeda'],
                'commission' => ['percent' => 5, 'amount' => 12500],
                'charge' => ['type' => 'Hipoteca', 'amount' => 80000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-4001')->firstOrFail();

    expect($p->internal_name)->toBe('Casa da esquina (Sr. Silva)')
        ->and($p->typology)->toBe('T3')
        ->and($p->admin['contract']['number'])->toBe('C-2026/14')
        ->and($p->admin['contract']['auto_renew'])->toBeTrue()
        ->and($p->admin['keys']['notes'])->toBe('Chaveiro 12')
        ->and($p->admin['energy']['number'])->toBe('SCE123456')
        ->and($p->admin['energy']['emissions_level'])->toBe('B2')
        ->and($p->admin['tax']['office_code'])->toBe('19')
        ->and($p->admin['tax']['office'])->toBe('AGUEDA')
        ->and($p->admin['sync']['block_import'])->toBeTrue()
        ->and($p->admin['use_licence']['issuer'])->toBe('CM Águeda')
        ->and($p->admin['commission']['percent'])->toBe(5)
        ->and($p->admin['charge']['type'])->toBe('Hipoteca');
});

it('nada do separador Interna aparece no site', function () {
    $p = Property::factory()->create([
        'internal_name' => 'Casa da esquina (Sr. Silva)',
        'status_reason' => 'Proprietário em viagem',
        'admin' => [
            'contract' => ['number' => 'C-2026/14'],
            'keys' => ['notes' => 'Chaveiro 12'],
            'commission' => ['percent' => 5, 'amount' => 12500],
            'tax' => ['matrix_number' => '4567'],
        ],
        'documents' => [['url' => 'documentos/caderneta.pdf']],
    ]);

    $ficha = $this->get(route('property.show', $p))->assertOk()->getContent();
    $lista = $this->get(route('buy'))->assertOk()->getContent();

    foreach ([$ficha, $lista] as $html) {
        expect($html)->not->toContain('Casa da esquina')
            ->and($html)->not->toContain('C-2026/14')
            ->and($html)->not->toContain('Chaveiro 12')
            ->and($html)->not->toContain('12500')
            ->and($html)->not->toContain('4567')
            ->and($html)->not->toContain('caderneta.pdf')
            ->and($html)->not->toContain('Proprietário em viagem');
    }
});

it('imóvel vendido ou fora de mercado sai das listagens e a ficha responde 410', function () {
    $vendido = Property::factory()->create(['is_sold' => true]);
    $foraMercado = Property::factory()->create(['off_market' => true]);
    $normal = Property::factory()->create();

    $html = $this->get(route('buy'))->assertOk()->getContent();

    expect($html)->toContain($normal->slug)
        ->and($html)->not->toContain($vendido->slug)
        ->and($html)->not->toContain($foraMercado->slug);

    $this->get(route('property.show', $vendido))->assertStatus(410);
    $this->get(route('property.show', $foraMercado))->assertStatus(410);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee($vendido->slug);
});

it('preço escondido mostra "Preço sob consulta" e sai do JSON-LD', function () {
    $p = Property::factory()->create(['price' => 450000, 'price_visible' => false]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain(__('ui.property.price_on_request'))
        ->and($html)->not->toContain('450 000')
        ->and($html)->not->toContain('"price":"450000');
});

it('os documentos ficam em disco privado, fora de public/', function () {
    $upload = FileUpload::make('documents')->disk('local')->directory('documentos');

    expect($upload->getDiskName())->toBe('local')
        ->and(config('filesystems.disks.local.root'))->not->toContain('public');
});

it('editar mantém os dados internos que não foram tocados', function () {
    $p = Property::factory()->create([
        'admin' => ['contract' => ['number' => 'C-1'], 'keys' => ['has' => true]],
    ]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->fillForm(['internal_name' => 'Nome novo'])
        ->call('save')
        ->assertHasNoFormErrors();

    $p->refresh();
    expect($p->internal_name)->toBe('Nome novo')
        ->and($p->admin['contract']['number'])->toBe('C-1')
        ->and($p->admin['keys']['has'])->toBeTrue();
});

it('a lista de imóveis tem as colunas da grelha do CRM', function () {
    $p = Property::factory()->create([
        'reference' => 'MF-9001',
        'property_type' => 'Moradia',
        'city' => 'Espinho',
        'locality' => 'Anta',
        'bedrooms' => 3,
        'broker' => ['name' => 'Ana Silva'],
        'admin' => ['keys' => ['has' => true, 'notes' => 'No cofre'], 'tags' => ['Urgente', 'Baixou preço']],
    ]);

    $lista = Livewire::test(ListProperties::class);

    foreach (['reference', 'cover_photo.url', 'property_type', 'city', 'locality', 'bedrooms',
        'price', 'keys', 'broker.name', 'view', 'status', 'tags'] as $coluna) {
        $lista->assertCanRenderTableColumn($coluna);
    }

    $lista->assertTableColumnStateSet('keys', true, $p)
        ->assertTableColumnStateSet('broker.name', 'Ana Silva', $p)
        ->assertTableColumnStateSet('locality', 'Anta', $p)
        ->assertTableColumnStateSet('status', 'Publicada', $p)
        ->assertTableColumnStateSet('tags', ['Urgente', 'Baixou preço'], $p);
});

it('a coluna Estado acompanha vendida, fora de mercado e retirada', function () {
    $p = Property::factory()->create();
    $lista = fn () => Livewire::test(ListProperties::class);

    $lista()->assertTableColumnStateSet('status', 'Publicada', $p);

    $p->update(['is_active' => false]);
    $lista()->assertTableColumnStateSet('status', 'Retirada', $p->fresh());

    $p->update(['is_active' => true, 'off_market' => true]);
    $lista()->assertTableColumnStateSet('status', 'Fora de mercado', $p->fresh());

    $p->update(['off_market' => false, 'is_sold' => true]);
    $lista()->assertTableColumnStateSet('status', 'Vendida', $p->fresh());
});

it('a coluna Visualizar só oferece link ao que o site mostra', function () {
    $publicada = Property::factory()->create(['reference' => 'MF-9002']);
    $vendida = Property::factory()->create(['reference' => 'MF-9003', 'is_sold' => true]);

    Livewire::test(ListProperties::class)
        ->assertTableColumnStateSet('view', 'Ver no site', $publicada)
        ->assertTableColumnStateSet('view', '—', $vendida);
});

it('as etiquetas são internas e nunca aparecem no site', function () {
    $p = Property::factory()->create(['admin' => ['tags' => ['Segredo interno']]]);

    $this->get(route('property.show', $p))->assertOk()->assertDontSee('Segredo interno');
    $this->get(route('buy'))->assertOk()->assertDontSee('Segredo interno');
});

it('o estado interno "Actual", o motivo e os monitores ficam guardados e fora do site', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-5001',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'city' => 'Espinho',
            'energy_rating' => 'C',
            'admin' => ['status' => Property::STATUS_ACTIVE, 'monitors' => ['Montra da rua']],
            'status_reason' => 'Em avaliação',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-5001')->firstOrFail();

    expect($p->admin['status'])->toBe(Property::STATUS_ACTIVE)
        ->and($p->admin['monitors'])->toBe(['Montra da rua'])
        ->and($p->status_reason)->toBe('Em avaliação')
        ->and($p->isPublishable())->toBeTrue();

    // Guardados, mas nada disto sai no site.
    $this->get(route('buy'))->assertOk()->assertSee('MF-5001');
    $this->get(route('property.show', $p))->assertOk()
        ->assertDontSee('Montra da rua')
        ->assertDontSee('Em avaliação');
});

it('"Actual: Inativa" retira a ficha do site, mesmo com "Visível no website" ligado', function () {
    $p = Property::factory()->create([
        'reference' => 'MF-6001',
        'is_active' => true,
        'admin' => ['status' => Property::STATUS_INACTIVE],
    ]);

    expect($p->isPublishable())->toBeFalse()
        ->and(Property::query()->active()->count())->toBe(0);

    $this->get(route('buy'))->assertOk()->assertDontSee('MF-6001');
    $this->get(route('property.show', $p))->assertGone();
});

it('as fichas sem "Actual" contam como ativas', function () {
    $p = Property::factory()->create(['reference' => 'MF-6002', 'admin' => []]);

    expect($p->internalStatus())->toBe(Property::STATUS_ACTIVE)
        ->and($p->isPublishable())->toBeTrue()
        ->and(Property::query()->active()->count())->toBe(1);

    $this->get(route('buy'))->assertOk()->assertSee('MF-6002');
});

it('marcar "Inativa" no formulário desliga o "Visível no website"', function () {
    $p = Property::factory()->create(['is_active' => true, 'admin' => ['status' => Property::STATUS_ACTIVE]]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->assertFormSet(['is_active' => true])
        ->fillForm(['admin' => ['status' => Property::STATUS_INACTIVE]])
        ->assertFormSet(['is_active' => false]);
});

it('mudar o "Actual" fica registado no histórico', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $p = Property::factory()->create(['admin' => ['status' => Property::STATUS_ACTIVE]]);
    $p->update(['admin' => ['status' => Property::STATUS_INACTIVE]]);

    $registo = PropertyActivity::query()->latest('id')->first();

    expect($registo->type)->toBe('status')
        ->and($registo->detail)->toBe(Property::STATUS_INACTIVE)
        ->and($registo->user_id)->toBe($user->id);
});

it('os documentos guardam nome, visível, categoria e os campos herdados do CRM', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-7001',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'city' => 'Espinho',
            'energy_rating' => 'C',
            'documents' => [[
                'file' => ['documentos/caderneta.pdf'],
                'name' => 'Caderneta predial',
                'visible' => true,
                'category' => 'Caderneta predial',
                'portals' => false,
                'predefined_reply' => false,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $doc = Property::where('reference', 'MF-7001')->firstOrFail()->documents[0];

    expect($doc['file'])->toBe('documentos/caderneta.pdf')
        ->and($doc['name'])->toBe('Caderneta predial')
        ->and($doc['visible'])->toBeTrue()
        ->and($doc['category'])->toBe('Caderneta predial');
});

it('a ficha tem o cabeçalho do CRM: Ver, Ações, Gravar e Sair', function () {
    $p = Property::factory()->create(['reference' => 'MF-8001']);

    $page = Livewire::test(EditProperty::class, ['record' => $p->slug]);

    foreach (['verNoWebsite', 'smartview', 'portais', 'partilhar', 'imprimir', 'delete', 'gravar', 'sair'] as $action) {
        $page->assertActionExists($action);
    }

    // Smartview e Portais são serviços do CRM que não existem: ficam desativados.
    $page->assertActionDisabled('smartview')
        ->assertActionDisabled('portais')
        ->assertActionEnabled('verNoWebsite')
        ->assertActionEnabled('partilhar');
});

it('numa ficha fora do site, Ver no website e Partilhar ficam desativados', function () {
    $p = Property::factory()->create(['is_sold' => true]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->assertActionDisabled('verNoWebsite')
        ->assertActionDisabled('partilhar');
});

it('o botão Gravar do cabeçalho grava mesmo', function () {
    $p = Property::factory()->create(['internal_name' => 'Antes']);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->fillForm(['internal_name' => 'Depois'])
        ->callAction('gravar');

    expect($p->fresh()->internal_name)->toBe('Depois');
});

it('a criação tem o mesmo cabeçalho, com Ver e Ações à espera da gravação', function () {
    Livewire::test(CreateProperty::class)
        ->assertActionExists('gravar')
        ->assertActionExists('sair')
        ->assertActionDisabled('verNoWebsite')
        ->assertActionDisabled('partilhar')
        ->assertActionDisabled('apagar')
        ->fillForm([
            'reference' => 'MF-8100',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'city' => 'Espinho',
            'energy_rating' => 'C',
        ])
        ->callAction('gravar');

    expect(Property::where('reference', 'MF-8100')->exists())->toBeTrue();
});

it('as comodidades do Detalhes caem no array features e os campos com valor em details', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-9100',
            'business_type' => 'sale',
            'property_type' => 'Moradia',
            'city' => 'Espinho',
            'energy_rating' => 'B',
            'det_features_a' => ['terraço', 'garagem'],
            'det_views' => ['vista mar', 'vista jardim'],
            'det_features_c' => ['licença turística'],
            'det_features_e' => ['mobilado'],
            'det_features_extra' => ['painéis solares'],
            'details' => [
                'floors' => 2,
                'solar_orientation' => ['Sul', 'Oeste'],
                'orientation' => 'Exterior',
                'occupancy' => 'Livre',
                'renovation_year' => 2021,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-9100')->firstOrFail();

    expect($p->features)->toBe(['terraço', 'garagem', 'vista mar', 'vista jardim', 'licença turística', 'mobilado', 'painéis solares'])
        ->and($p->details['floors'])->toBe(2)
        ->and($p->details['solar_orientation'])->toBe(['Sul', 'Oeste'])
        ->and($p->details['orientation'])->toBe('Exterior')
        ->and($p->details['occupancy'])->toBe('Livre');

    // As comodidades continuam a alimentar o filtro do site (índice GIN).
    expect(Property::query()->withFeatures(['vista mar'])->count())->toBe(1);
});

it('editar divide as features pelos grupos e preserva as que não pertencem a nenhum', function () {
    $p = Property::factory()->create(['features' => ['garagem', 'vista rio', 'cozinha equipada', 'painéis solares']]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->assertFormSet([
            'det_features_a' => ['garagem'],
            'det_views' => ['vista rio'],
            'det_interior' => ['cozinha equipada'],
            'det_features_extra' => ['painéis solares'],
        ])
        ->fillForm(['det_features_a' => ['garagem', 'cave']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($p->fresh()->features)->toContain('garagem', 'cave', 'vista rio', 'cozinha equipada', 'painéis solares');
});

it('o Interior e o Exterior também alimentam o array features', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-9200',
            'business_type' => 'sale',
            'property_type' => 'Moradia',
            'city' => 'Espinho',
            'energy_rating' => 'A',
            'det_interior' => ['cozinha equipada', 'lareira'],
            'det_exterior' => ['piscina'],
            'det_proximity' => ['proximidade: praia', 'proximidade: escolas'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-9200')->firstOrFail();

    expect($p->features)->toBe(['cozinha equipada', 'lareira', 'piscina', 'proximidade: praia', 'proximidade: escolas'])
        ->and(Property::query()->withFeatures(['piscina'])->count())->toBe(1);

    // E ao editar, voltam aos sub-separadores certos.
    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->assertFormSet([
            'det_interior' => ['cozinha equipada', 'lareira'],
            'det_exterior' => ['piscina'],
            'det_proximity' => ['proximidade: praia', 'proximidade: escolas'],
            'det_features_extra' => [],
        ]);
});

it('sem separador de descrições, o título é gerado a partir do tipo, tipologia e concelho', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-9300',
            'business_type' => 'sale',
            'property_type' => 'Moradia',
            'typology' => 'T3',
            'city' => 'Espinho',
            'energy_rating' => 'B',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Property::where('reference', 'MF-9300')->firstOrFail()->title)->toBe('Moradia T3 em Espinho');
});

it('editar não apaga o título nem a descrição que já existiam', function () {
    $p = Property::factory()->create([
        'translations' => ['pt' => ['title' => 'Título escrito à mão', 'description' => 'Descrição antiga.']],
    ]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->fillForm(['bedrooms' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    $p->refresh();

    expect($p->title)->toBe('Título escrito à mão')
        ->and($p->translations['pt']['description'])->toBe('Descrição antiga.')
        ->and($p->bedrooms)->toBe(5);
});

it('o geocodificador devolve as coordenadas da morada', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '40.5762594', 'lon' => '-8.4490490', 'display_name' => 'Águeda, Aveiro, Portugal',
        ]]),
    ]);

    $coords = Geocoder::search([
        'address' => 'Rua Direita', 'street_number' => '12', 'zipcode' => '3750-100',
        'locality' => 'Aguada de Cima', 'city' => 'Águeda', 'district' => 'Aveiro', 'country' => 'PT',
    ]);

    expect($coords['lat'])->toBe('40.5762594')
        ->and($coords['lon'])->toBe('-8.449049')
        ->and($coords['label'])->toContain('Águeda');

    // A morada segue completa, e identificamo-nos como o Nominatim exige.
    Http::assertSent(function ($request) {
        return str_contains(urldecode($request->url()), 'Rua Direita')
            && str_contains($request->url(), 'countrycodes=pt')
            && $request->hasHeader('User-Agent');
    });
});

it('sem morada ou sem resultado, o geocodificador não inventa coordenadas', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([]),
    ]);

    expect(Geocoder::search([]))->toBeNull()
        ->and(Geocoder::search(['city' => 'Sítio que não existe']))->toBeNull();

    // Sem nada preenchido nem se chega a contactar o serviço.
    Http::assertSentCount(1);
});

it('as coordenadas continuam escondidas quando o mapa não é visível', function () {
    $p = Property::factory()->create(['gmap_visible' => false, 'lat' => '40.5762594', 'lon' => '-8.449049']);

    expect($p->coordinates)->toBeNull();

    $this->get(route('property.show', $p))->assertOk()
        ->assertDontSee('40.5762594')
        ->assertDontSee('-8.449049');
});
