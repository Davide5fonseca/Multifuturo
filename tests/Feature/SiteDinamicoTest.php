<?php

/*
 * Funcionalidades dinâmicas do site: sugestões da pesquisa, scroll infinito nas
 * listagens, imóveis vistos recentemente e partilha da ficha.
 */

use App\Livewire\PropertyListing;
use App\Models\Property;
use App\Support\PropertyCache;
use Livewire\Livewire;

beforeEach(fn () => PropertyCache::flush());

it('a pesquisa sugere concelhos, freguesias e imóveis a partir de duas letras', function () {
    Property::factory()->create([
        'city' => 'Espinho', 'locality' => 'Anta', 'reference' => 'MF-7001',
        'translations' => ['pt' => ['title' => 'Moradia T3 em Espinho']],
    ]);
    Property::factory()->create(['city' => 'Lisboa', 'locality' => 'Alvalade', 'reference' => 'MF-7002']);

    // Uma letra é cedo demais: nada de pedidos à base de dados por nada.
    expect($this->getJson(route('search.suggest', ['q' => 'e']))->assertOk()->json('items'))->toBe([]);

    $items = $this->getJson(route('search.suggest', ['q' => 'esp']))->assertOk()->json('items');

    expect($items)->not->toBeEmpty()
        ->and(collect($items)->pluck('label'))->toContain('Espinho', 'Moradia T3 em Espinho')
        ->and(collect($items)->firstWhere('label', 'Espinho')['url'])->toContain('concelho=Espinho');

    // A freguesia leva o concelho consigo, para o filtro fazer sentido.
    $anta = collect($this->getJson(route('search.suggest', ['q' => 'ant']))->json('items'))->firstWhere('label', 'Anta');
    expect($anta['hint'])->toBe('Espinho')
        ->and($anta['url'])->toContain('freguesia=Anta');

    // A finalidade escolhida no formulário decide a listagem de destino.
    $items = $this->getJson(route('search.suggest', ['q' => 'esp', 'f' => 'rent']))->json('items');
    expect($items[0]['url'])->toContain('/arrendar');
});

it('as sugestões só mostram imóveis publicados', function () {
    Property::factory()->inactive()->create([
        'city' => 'Guimarães', 'reference' => 'MF-7003',
        'translations' => ['pt' => ['title' => 'Escondido de Guimarães']],
    ]);

    $items = $this->getJson(route('search.suggest', ['q' => 'guima']))->assertOk()->json('items');

    expect($items)->toBe([]);
});

it('a listagem carrega mais resultados sem trocar de página', function () {
    Property::factory()->count(30)->create(['business_type' => 'sale']);

    $lista = Livewire::test(PropertyListing::class, ['businessType' => 'sale']);

    expect($lista->instance()->properties()->count())->toBe(PropertyListing::PER_PAGE)
        ->and($lista->instance()->hasMore())->toBeTrue();

    $lista->call('loadMore');
    expect($lista->instance()->properties()->count())->toBe(PropertyListing::PER_PAGE * 2);

    $lista->call('loadMore');
    expect($lista->instance()->properties()->count())->toBe(30)
        // Chegou ao fim: o botão desaparece.
        ->and($lista->instance()->hasMore())->toBeFalse();

    // Um filtro novo recomeça do princípio.
    $lista->set('sort', 'price_asc');
    expect($lista->instance()->properties()->count())->toBe(PropertyListing::PER_PAGE);
});

it('a paginação numerada continua a existir para quem não tem JavaScript', function () {
    Property::factory()->count(20)->create(['business_type' => 'sale']);

    $html = $this->get(route('buy'))->assertOk()->getContent();

    expect($html)->toContain('?page=2')
        ->toContain(__('ui.listing.load_more'));

    // A segunda página continua a responder e a mostrar os imóveis seguintes.
    $lista = Livewire::test(PropertyListing::class, ['businessType' => 'sale'])->call('setPage', 2);
    expect($lista->instance()->properties()->count())->toBe(8)
        ->and($lista->instance()->properties()->currentPage())->toBe(2);

    // Voltar à página 1 depois de ter carregado blocos recomeça do princípio.
    $lista->call('loadMore')->call('setPage', 1);
    expect($lista->instance()->properties()->count())->toBe(PropertyListing::PER_PAGE);
});

it('o mapa da listagem só leva os imóveis com localização pública', function () {
    $comMapa = Property::factory()->create(['business_type' => 'sale', 'city' => 'Espinho', 'lat' => 41.007, 'lon' => -8.641, 'gmap_visible' => true]);
    Property::factory()->create(['business_type' => 'sale', 'city' => 'Aveiro', 'lat' => 40.640, 'lon' => -8.653, 'gmap_visible' => false]);
    Property::factory()->create(['business_type' => 'sale', 'city' => 'Braga', 'lat' => null, 'lon' => null]);

    $pontos = Livewire::test(PropertyListing::class, ['businessType' => 'sale'])->instance()->mapPoints();

    expect($pontos)->toHaveCount(1)
        ->and($pontos[0]['lat'])->toBe(41.007)
        ->and($pontos[0]['url'])->toBe(route('property.show', $comMapa));

    // O botão do mapa aparece na listagem, e o Leaflet vem do nosso servidor.
    $html = $this->get(route('buy'))->assertOk()->getContent();
    expect($html)->toContain(__('ui.listing.map_view'))
        ->toContain('resultsMap(')
        ->toContain('leaflet.css'); // @js() escapa as barras: procura-se só o nome do ficheiro

    // Sem nenhum imóvel com localização pública não há mapa nenhum.
    Property::query()->update(['gmap_visible' => false]);
    PropertyCache::flush();
    expect($this->get(route('buy'))->getContent())->not->toContain(__('ui.listing.map_view'));
});

it('os cartões dos vistos recentemente vêm pela ordem pedida e só de imóveis publicados', function () {
    $a = Property::factory()->create(['city' => 'Porto']);
    $b = Property::factory()->create(['city' => 'Braga']);
    $fora = Property::factory()->inactive()->create(['city' => 'Faro']);

    $html = $this->get(route('property.cards', ['slugs' => "{$b->slug},{$a->slug},{$fora->slug}"]))
        ->assertOk()->getContent();

    expect($html)->toContain($b->slug)->toContain($a->slug)->not->toContain($fora->slug)
        // A ordem pedida é a ordem mostrada (o mais recente primeiro).
        ->and(strpos($html, $b->slug))->toBeLessThan(strpos($html, $a->slug));

    // Sem slugs não há cartões nenhuns.
    expect(trim($this->get(route('property.cards'))->assertOk()->getContent()))->toBe('');

    // Slugs inventados não passam pelo filtro.
    expect(trim($this->get(route('property.cards', ['slugs' => '../etc/passwd,<script>']))->assertOk()->getContent()))->toBe('');
});

it('a ficha regista-se nos vistos recentemente e tem partilha', function () {
    $p = Property::factory()->create();

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain('$store.recent.push')
        ->toContain(__('ui.property.share'))
        ->toContain('navigator.share')
        ->toContain(__('ui.property.recent'));
});
