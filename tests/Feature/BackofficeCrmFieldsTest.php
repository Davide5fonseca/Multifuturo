<?php

/*
 * Campos equivalentes ao antigo CRM: dados internos (jsonb `admin`), estados
 * (vendida / fora de mercado), preço visível e documentos privados.
 */

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Models\Property;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
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
            'translations' => ['pt' => ['title' => 'T3 em Águeda']],
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
