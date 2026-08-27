<?php

/*
 * Simulador "Quanto vale a minha casa?": tabela de €/m² (referência do
 * backoffice > mediana da carteira), conta, página e pedido com a estimativa.
 */

use App\Filament\Resources\ReferencePrices\Pages\CreateReferencePrice;
use App\Filament\Resources\ReferencePrices\Pages\ListReferencePrices;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Property;
use App\Models\ReferencePrice;
use App\Models\User;
use App\Support\PropertyCache;
use App\Support\Valuation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => PropertyCache::flush());

it('estima a partir do valor de referência: ±10 %, arredondado ao milhar, fator do estado', function () {
    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2500]);

    expect(Valuation::estimate('Sintra', 'apartment', 100, 'good'))
        ->toMatchArray(['min' => 225000, 'mid' => 250000, 'max' => 275000, 'source' => 'reference'])
        ->and(Valuation::estimate('Sintra', 'apartment', 100, 'renovate')['mid'])->toBe(213000)
        ->and(Valuation::estimate('Sintra', 'apartment', 100, 'new')['mid'])->toBe(270000)
        ->and(Valuation::estimate('Sintra', 'house', 100))->toBeNull()
        ->and(Valuation::estimate('Lisboa', 'apartment', 100))->toBeNull();
});

it('sem valor de referência usa a mediana das vendas publicadas no concelho, com pelo menos 3', function () {
    Property::factory()->count(3)->sequence(
        ['price' => 200000], ['price' => 330000], ['price' => 1000000],
    )->create(['city' => 'Cascais', 'property_type' => 'Apartamento', 'house_area' => 100, 'price_visible' => true]);
    Property::factory()->count(2)->create(['city' => 'Cascais', 'property_type' => 'Moradia', 'price_visible' => true]);

    $cascais = Valuation::table()['Cascais']['types'];

    expect($cascais['apartment'])->toMatchArray(['ppm2' => 3300.0, 'source' => 'portfolio', 'n' => 3])
        ->and($cascais)->not->toHaveKey('house');
});

it('preço sob consulta, arrendamentos e fichas retiradas não entram nos comparáveis', function () {
    Property::factory()->count(3)->create(['city' => 'Oeiras', 'property_type' => 'Apartamento', 'house_area' => 100, 'price' => 300000, 'price_visible' => false]);
    Property::factory()->count(3)->forRent()->create(['city' => 'Oeiras', 'property_type' => 'Apartamento', 'house_area' => 100, 'price' => 1000]);
    Property::factory()->count(3)->create(['city' => 'Oeiras', 'property_type' => 'Apartamento', 'house_area' => 100, 'price' => 300000, 'is_active' => false]);

    expect(Valuation::table())->not->toHaveKey('Oeiras');
});

it('o valor de referência do backoffice sobrepõe-se à carteira', function () {
    Property::factory()->count(3)->create(['city' => 'Lisboa', 'property_type' => 'Apartamento', 'house_area' => 100, 'price' => 500000, 'price_visible' => true]);
    ReferencePrice::create(['city' => 'Lisboa', 'property_type' => 'apartment', 'price_per_m2' => 4000]);

    expect(Valuation::table()['Lisboa']['types']['apartment'])->toMatchArray(['ppm2' => 4000.0, 'source' => 'reference']);
});

it('a página mostra o simulador com os concelhos disponíveis, ou o aviso quando não há valores', function () {
    $this->get(route('valuation'))->assertOk()
        ->assertDontSee('data-valuation', false)
        ->assertSee('A estimativa imediata está em preparação');

    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2500]);

    $html = $this->get(route('valuation'))->assertOk()
        ->assertSee('data-valuation', false)
        ->assertSee('<datalist id="val-cities">', false)
        ->assertSee('<option value="Sintra">', false)
        ->assertSee('Estimativa imediata e avaliação gratuita')
        // Um só cartão: o simulador é o passo 1 do formulário; o imóvel segue escondido.
        ->assertSee('1 · O seu imóvel')
        ->assertSee('2 · Os seus dados')
        ->assertSee('<input type="hidden" id="lead-valuation-city" name="payload[city]"', false)
        ->assertSee('<input type="hidden" id="lead-valuation-locality" name="payload[locality]"', false)
        ->assertDontSee('name="payload[bedrooms]"', false)
        ->getContent();

    // O x-data vive num atributo HTML: uma aspa dupla lá dentro (num comentário,
    // num nome de freguesia) fecha-o e o JavaScript aparece na página.
    $inicio = strpos($html, 'x-data="{'.PHP_EOL.'        table:');
    $fim = strpos($html, '"', $inicio + 9);
    expect(substr($html, $fim - 5, 28))->toBe('    }"'.'
'.'    x-effect="emit()"');

    $this->get('/en/quanto-vale-a-minha-casa')->assertOk()
        ->assertSee('Instant estimate and free valuation')
        ->assertSee('1 · Your property');
});

it('uma freguesia com valor próprio sobrepõe-se ao concelho; sem ele, usa-se o concelho', function () {
    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'house', 'price_per_m2' => 2800]);
    ReferencePrice::create(['city' => 'Sintra', 'locality' => 'Colares', 'property_type' => 'house', 'price_per_m2' => 4000]);

    expect(Valuation::estimate('Sintra', 'house', 100, 'good', 'Colares'))->toMatchArray(['mid' => 400000, 'place' => 'Colares, Sintra'])
        ->and(Valuation::estimate('Sintra', 'house', 100, 'good', 'Algueirão'))->toMatchArray(['mid' => 280000, 'place' => 'Sintra'])
        ->and(Valuation::estimate('Sintra', 'apartment', 100, 'good', 'Colares'))->toBeNull();

    $this->get(route('valuation'))->assertOk()->assertSee('id="val-localities"', false);
});

it('o pedido de avaliação guarda a estimativa que a pessoa viu', function () {
    Notification::fake();

    $this->post(route('leads.store'), [
        'source' => 'valuation',
        'name' => 'Rui Teste',
        'email' => 'rui@example.test',
        'form_ts' => StoreLeadRequest::signedTimestamp(time() - 30),
        'payload' => ['city' => 'Sintra', 'locality' => 'Colares', 'property_type' => 'Apartamento', 'area' => 100, 'condition' => 'Bom estado', 'estimate' => '225 000 € – 275 000 €'],
    ])->assertRedirect();

    expect(Lead::first()->payload)->toMatchArray(['city' => 'Sintra', 'locality' => 'Colares', 'estimate' => '225 000 € – 275 000 €']);
});

it('o backoffice lista e cria valores de referência, sem repetir concelho e tipo', function () {
    $this->actingAs(User::factory()->create());
    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2500]);

    Livewire::test(ListReferencePrices::class)->assertOk()->assertSee('Sintra')->assertSee('2 500 €/m²');

    Livewire::test(CreateReferencePrice::class)
        ->fillForm(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2600])
        ->call('create')
        ->assertHasFormErrors(['city']);

    Livewire::test(CreateReferencePrice::class)
        ->fillForm(['city' => 'Sintra', 'property_type' => 'house', 'price_per_m2' => 3800])
        ->call('create')
        ->assertHasNoFormErrors();

    $casa = ReferencePrice::where('property_type', 'house')->first();

    expect($casa->price_per_m2)->toBe('3800.00')
        ->and($casa->source)->toBe('manual')
        ->and($casa->locality)->toBe('');
});
