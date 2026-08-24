<?php

/*
 * Backoffice: criar e editar imóveis (o que substitui o CRM). Cobre os
 * automatismos (internal_id, slug estável, hash, cache) e a passagem das
 * fotografias de upload para o formato que o site consome.
 */

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Models\Property;
use App\Models\User;
use App\Support\PropertyCache;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('cria um imóvel com os campos do formulário e gera os campos técnicos', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-3001',
            'business_type' => 'sale',
            'property_type' => 'Moradia',
            'det_features_a' => ['garagem'],
            'det_features_extra' => ['jardim'],
            'price' => 750000,
            'house_area' => 210,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'city' => 'Cascais',
            'locality' => 'Alcabideche',
            'energy_rating' => 'B',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-3001')->firstOrFail();

    expect($p->internal_id)->toStartWith('BO-')
        ->and($p->slug)->toBe('moradia-cascais-mf-3001')
        ->and($p->payload_hash)->toHaveLength(64)
        ->and($p->title)->toBe('Moradia em Cascais')   // gerado: o CRM trata os textos em "Descrições"
        ->and($p->features)->toBe(['garagem', 'jardim'])
        ->and((float) $p->price)->toBe(750000.0)
        ->and($p->is_active)->toBeTrue()
        ->and($p->gmap_visible)->toBeFalse()   // default seguro
        ->and($p->photos)->toBe([]);
});

it('o imóvel criado no backoffice aparece imediatamente no site', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-3002',
            'business_type' => 'rent',
            'property_type' => 'Apartamento',
            'city' => 'Oeiras',
            'typology' => 'T2',
            'energy_rating' => 'C',
            'price' => 1100,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // O título é gerado a partir do tipo, tipologia e concelho.
    $this->get(route('rent'))->assertOk()->assertSee('Apartamento T2 em Oeiras');
    $this->get(route('buy'))->assertOk()->assertDontSee('Apartamento T2 em Oeiras');
});

it('editar não recalcula o slug, mesmo mudando o concelho', function () {
    $p = Property::factory()->create(['reference' => 'MF-3003', 'city' => 'Cascais', 'property_type' => 'Apartamento']);
    $slug = $p->slug;

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->fillForm([
            'city' => 'Oeiras',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $p->refresh();
    expect($p->slug)->toBe($slug)
        ->and($p->city)->toBe('Oeiras');
});

it('editar preserva fotografias externas (importadas do CRM) que não são uploads locais', function () {
    $p = Property::factory()->create([
        'photos' => [
            ['url' => 'https://cdn.proppy.app/x/1.jpg', 'order' => 1],
            ['url' => '/storage/imoveis/local.jpg', 'order' => 2],
        ],
    ]);

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->call('save')
        ->assertHasNoFormErrors();

    // A externa sobrevive à edição (o upload local só existe no disco em uso real).
    expect(collect($p->refresh()->photos)->pluck('url')->all())
        ->toContain('https://cdn.proppy.app/x/1.jpg');
});

it('a referência não pode repetir-se', function () {
    Property::factory()->create(['reference' => 'MF-3004']);

    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-3004',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'city' => 'Lisboa',
            'energy_rating' => 'C',
        ])
        ->call('create')
        ->assertHasFormErrors(['reference']);
});

it('gravar no backoffice invalida a cache do site', function () {
    Property::factory()->create();
    PropertyCache::remember('sentinela', fn () => 'valor');

    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-3005',
            'business_type' => 'sale',
            'property_type' => 'Loja / comércio',
            'city' => 'Lisboa',
            'energy_rating' => 'D',
        ])
        ->call('create');

    expect(PropertyCache::store()->get('props:sentinela'))->toBeNull();
});
