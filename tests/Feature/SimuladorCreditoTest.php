<?php

/*
 * Simulador de crédito habitação na ficha do imóvel. O cálculo corre no
 * browser; aqui garante-se onde aparece, com que preço, e onde NÃO aparece.
 */

use App\Enums\BusinessType;
use App\Models\Property;

it('a ficha de uma venda tem o simulador com o preço do imóvel', function () {
    $p = Property::factory()->create(['business_type' => BusinessType::Sale, 'price' => 385000, 'price_visible' => true]);

    $this->get(route('property.show', $p))->assertOk()
        ->assertSee(__('ui.simulator.title'))
        ->assertSee('data-price="385000"', escape: false)
        ->assertSee(__('ui.simulator.note'));
});

it('um arrendamento não tem simulador de crédito', function () {
    $p = Property::factory()->create(['business_type' => BusinessType::Rent, 'price' => 950, 'price_visible' => true]);

    $this->get(route('property.show', $p))->assertOk()
        ->assertDontSee('data-simulator', escape: false);
});

it('sem preço público não há simulador — senão revelava o preço escondido', function () {
    $escondido = Property::factory()->create(['business_type' => BusinessType::Sale, 'price' => 520000, 'price_visible' => false]);
    $semPreco = Property::factory()->create(['business_type' => BusinessType::Sale, 'price' => null]);

    $this->get(route('property.show', $escondido))->assertOk()
        ->assertDontSee('data-simulator', escape: false)
        ->assertDontSee('520000');

    $this->get(route('property.show', $semPreco))->assertOk()
        ->assertDontSee('data-simulator', escape: false);
});

it('o simulador está traduzido em inglês', function () {
    $p = Property::factory()->create(['business_type' => BusinessType::Sale, 'price' => 385000, 'price_visible' => true]);

    $this->get('/en/imoveis/'.$p->slug)->assertOk()
        ->assertSee('Mortgage simulator')
        ->assertSee('Estimated monthly instalment');
});
