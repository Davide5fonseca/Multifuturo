<?php

/*
 * Finalidades do imóvel — as seis do CRM, mapeadas nas duas listagens do site.
 */

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\Format;

it('as seis finalidades do CRM caem nas listagens certas', function () {
    Property::factory()->create(['business_type' => BusinessType::Sale, 'reference' => 'F-VENDA']);
    Property::factory()->create(['business_type' => BusinessType::Transfer, 'reference' => 'F-TRESP']);
    Property::factory()->create(['business_type' => BusinessType::Exchange, 'reference' => 'F-PERM']);
    Property::factory()->create(['business_type' => BusinessType::Rent, 'reference' => 'F-ANUAL']);
    Property::factory()->create(['business_type' => BusinessType::ShortTermRent, 'reference' => 'F-CURTO']);
    $ambas = Property::factory()->create(['business_type' => BusinessType::RentOrSale, 'reference' => 'F-AMBAS']);

    expect(Property::query()->forSale()->pluck('reference')->all())
        ->toEqualCanonicalizing(['F-VENDA', 'F-TRESP', 'F-PERM', 'F-AMBAS'])
        ->and(Property::query()->forRent()->pluck('reference')->all())
        ->toEqualCanonicalizing(['F-ANUAL', 'F-CURTO', 'F-AMBAS']);

    // O "arrendamento / venda" entra nas duas, mas o rasto da ficha aponta para Comprar.
    expect($ambas->business_type->routeName())->toBe('buy')
        ->and(BusinessType::ShortTermRent->routeName())->toBe('rent');
});

it('só o arrendamento puro mostra o preço por mês', function () {
    expect(Format::price(900, 'EUR', BusinessType::Rent))->toContain('mês')
        ->and(Format::price(900, 'EUR', BusinessType::ShortTermRent))->toContain('mês')
        ->and(Format::price(250000, 'EUR', BusinessType::RentOrSale))->not->toContain('mês')
        ->and(Format::price(250000, 'EUR', BusinessType::Transfer))->not->toContain('mês');
});

it('as listagens públicas mostram as finalidades mapeadas', function () {
    Property::factory()->create(['business_type' => BusinessType::Transfer, 'reference' => 'F-TRESP']);
    Property::factory()->create(['business_type' => BusinessType::RentOrSale, 'reference' => 'F-AMBAS']);

    $this->get(route('buy'))->assertOk()->assertSee('F-TRESP')->assertSee('F-AMBAS');
    $this->get(route('rent'))->assertOk()->assertSee('F-AMBAS')->assertDontSee('F-TRESP');
});

it('todas as finalidades têm rótulo em português', function () {
    foreach (BusinessType::cases() as $case) {
        expect($case->label())->not->toStartWith('ui.');
    }

    expect(BusinessType::options())->toHaveCount(6)
        ->and(BusinessType::options()['rent_short'])->toBe('Arrendamento curto prazo / férias');
});
