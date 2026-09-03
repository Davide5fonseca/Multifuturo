<?php

/*
 * Os quatro indicadores do painel: contas certas, linhas de tendência sem
 * buracos e o painel a atualizar-se sozinho.
 */

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Widgets\DashboardStats;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create(['is_admin' => true])));

/**
 * Os métodos do widget são protegidos (é assim que o Filament os declara):
 * chega-se a eles por reflexão, em vez de abrir a classe só para os testes.
 * O assertOk() garante, à parte, que o quadro renderiza mesmo sem rebentar.
 */
function doWidget(string $metodo): mixed
{
    $widget = Livewire::test(DashboardStats::class)->assertOk()->instance();
    $m = new ReflectionMethod($widget, $metodo);
    $m->setAccessible(true);

    return $m->invoke($widget);
}

it('os indicadores contam o que está no site e o que falta responder', function () {
    Property::factory()->count(3)->create(['business_type' => 'sale', 'price' => 100000, 'price_visible' => true, 'published_at' => now()]);
    Property::factory()->inactive()->create();
    Lead::factory()->create(['replied_at' => null, 'created_at' => now()->subDays(2)]);
    Lead::factory()->create(['replied_at' => now(), 'created_at' => now()->subDays(3)]);
    // Fora dos 30 dias: não entra na contagem do período.
    Lead::factory()->create(['replied_at' => now(), 'created_at' => now()->subDays(45)]);

    $valores = collect(doWidget('getStats'))->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()]);

    expect($valores['Imóveis no site'])->toBe('3')
        ->and($valores['Pedidos por responder'])->toBe('1')
        ->and($valores['Pedidos (30 dias)'])->toBe('2')
        // 3 × 100 000 €, só os que estão à venda com preço público.
        ->and($valores['Carteira à venda'])->toContain('300');
});

it('as linhas de tendência têm um ponto por período, mesmo nos dias sem nada', function () {
    Lead::factory()->create(['created_at' => now()]);

    $stats = collect(doWidget('getStats'))->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getChart()]);

    expect($stats['Pedidos (30 dias)'])->toHaveCount(14)
        ->and($stats['Imóveis no site'])->toHaveCount(6)
        // Nada em falta: um gráfico com buracos mentiria sobre a curva.
        ->and(array_filter($stats['Pedidos (30 dias)'], fn ($v) => ! is_int($v)))->toBe([]);
});

it('o painel refresca-se sozinho e o número por responder leva ao sítio certo', function () {
    Lead::factory()->create(['replied_at' => null]);

    expect(doWidget('getPollingInterval'))->toBe('30s');

    $porResponder = collect(doWidget('getStats'))->firstWhere(fn ($s) => $s->getLabel() === 'Pedidos por responder');
    expect($porResponder->getUrl())->toBe(route('filament.admin.resources.leads.index'))
        ->and($porResponder->getColor())->toBe('danger');
});

it('a pesquisa do topo encontra imóveis pela referência e pelo concelho', function () {
    $p = Property::factory()->create(['reference' => 'MF-9001', 'city' => 'Espinho']);

    $porReferencia = PropertyResource::getGlobalSearchResults('MF-9001');
    $porConcelho = PropertyResource::getGlobalSearchResults('Espinho');

    expect($porReferencia)->toHaveCount(1)
        ->and($porConcelho->first()->url)->toBe(PropertyResource::getUrl('edit', ['record' => $p]));
});
