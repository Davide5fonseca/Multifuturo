<?php

/*
 * Fase 4 — frontend público: homepage, listagens com filtros, ficha, zonas,
 * favoritos, sitemap e cache.
 */

use App\Events\PropertiesSynced;
use App\Livewire\PropertyListing;
use App\Models\Property;
use App\Models\Zone;
use App\Services\Casafari\SyncResult;
use App\Support\PropertyCache;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Homepage
|--------------------------------------------------------------------------
*/

it('a homepage mostra destaques, zonas e a banda de contacto', function () {
    Property::factory()->count(2)->featured()->create(['city' => 'Cascais']);
    Property::factory()->count(2)->create(['city' => 'Lisboa']);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain(__('ui.home_sections.featured'))
        ->and(substr_count($html, 'data-slug='))->toBe(4)   // 2 destaques + completa até 3+ com recentes (máx. 6)
        ->and($html)->toContain(route('zones.city', 'cascais'))
        ->and($html)->toContain(route('valuation'))
        ->and($html)->toContain('role="search"');
});

/*
|--------------------------------------------------------------------------
| Listagens
|--------------------------------------------------------------------------
*/

it('/comprar só mostra imóveis ativos para venda; /arrendar os de arrendamento', function () {
    $sale = Property::factory()->create();
    $rent = Property::factory()->forRent()->create();
    $inactive = Property::factory()->inactive()->create();

    $buy = $this->get(route('buy'))->assertOk()->getContent();
    $rentPage = $this->get(route('rent'))->assertOk()->getContent();

    expect($buy)->toContain($sale->slug)->not->toContain($rent->slug)->not->toContain($inactive->slug)
        ->and($rentPage)->toContain($rent->slug)->not->toContain($sale->slug);
});

it('lê os filtros da query string no primeiro render (URLs partilháveis)', function () {
    $a = Property::factory()->create(['bedrooms' => 3, 'city' => 'Cascais']);
    $b = Property::factory()->create(['bedrooms' => 1, 'city' => 'Cascais']);
    $c = Property::factory()->create(['bedrooms' => 4, 'city' => 'Lisboa']);

    Livewire::withQueryParams(['tipologia' => '3', 'concelho' => 'Cascais'])
        ->test(PropertyListing::class, ['businessType' => 'sale'])
        ->assertSee($a->slug)->assertDontSee($b->slug)->assertDontSee($c->slug);
});

it('filtra por preço e características e limpa os filtros', function () {
    $a = Property::factory()->create(['bedrooms' => 3, 'city' => 'Cascais', 'price' => 500000, 'features' => ['garagem']]);
    $b = Property::factory()->create(['bedrooms' => 1, 'city' => 'Cascais', 'price' => 200000, 'features' => ['varanda']]);
    $c = Property::factory()->create(['bedrooms' => 4, 'city' => 'Lisboa', 'price' => 900000, 'features' => ['garagem', 'piscina']]);

    Livewire::test(PropertyListing::class, ['businessType' => 'sale'])
        ->set('priceMax', '600000')
        ->assertSee($a->slug)->assertSee($b->slug)->assertDontSee($c->slug)
        ->set('features', ['garagem'])
        ->assertSee($a->slug)->assertDontSee($b->slug)
        ->call('clearFilters')
        ->assertSee($c->slug);

    // Query string via HTTP: o primeiro render já vem filtrado (SEO / partilha).
    $this->get(route('buy', ['tipologia' => 4]))->assertOk()->assertSee($c->slug)->assertDontSee($a->slug);
});

it('ordena por preço e por data', function () {
    $cheap = Property::factory()->create(['price' => 100000, 'crm_updated_at' => now()->subDays(10)]);
    $dear = Property::factory()->create(['price' => 900000, 'crm_updated_at' => now()->subDay()]);

    Livewire::test(PropertyListing::class, ['businessType' => 'sale'])
        ->set('sort', 'price_asc')->assertSeeInOrder([$cheap->slug, $dear->slug])
        ->set('sort', 'price_desc')->assertSeeInOrder([$dear->slug, $cheap->slug])
        ->set('sort', 'recent')->assertSeeInOrder([$dear->slug, $cheap->slug]);
});

it('pagina com 12 por página e sanitiza valores estranhos vindos do URL', function () {
    Property::factory()->count(15)->create();

    $component = Livewire::withQueryParams(['preco_min' => 'abc<script>', 'ordenar' => 'DROP', 'tipologia' => '99'])
        ->test(PropertyListing::class, ['businessType' => 'sale']);

    expect($component->get('priceMin'))->toBe('')
        ->and($component->get('sort'))->toBe('recent')
        ->and($component->get('bedrooms'))->toBe('');

    $html = $this->get(route('buy'))->assertOk()->getContent();
    expect(substr_count($html, 'data-slug='))->toBe(12);
    $this->get(route('buy', ['page' => 2]))->assertOk();
});

it('a pesquisa livre encontra por referência, concelho e título', function () {
    $p = Property::factory()->create(['reference' => 'MF-4242', 'city' => 'Óbidos', 'translations' => ['pt' => ['title' => 'Casa com vista para a lagoa', 'description' => '']]]);
    Property::factory()->create(['reference' => 'MF-1111', 'city' => 'Porto']);

    foreach (['MF-4242', 'óbidos', 'lagoa'] as $term) {
        Livewire::test(PropertyListing::class, ['businessType' => 'sale'])->set('search', $term)->assertSee($p->slug)->assertDontSee('MF-1111');
    }
});

/*
|--------------------------------------------------------------------------
| Ficha de imóvel
|--------------------------------------------------------------------------
*/

it('a ficha mostra os campos, o JSON-LD, o canonical e o formulário pré-preenchido', function () {
    $p = Property::factory()->create(['reference' => 'MF-777', 'energy_rating' => 'A', 'city' => 'Cascais']);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain('MF-777')
        ->and($html)->toContain('<link rel="canonical" href="'.route('property.show', $p).'">')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('"@type":"RealEstateListing"')
        ->and($html)->toContain('"identifier":"MF-777"')
        ->and($html)->toContain('property="og:image"')
        ->and($html)->toContain('name="property_slug" value="'.$p->slug.'"')
        ->and($html)->toContain('MF-777.'); // mensagem pré-preenchida com a referência
});

it('não expõe coordenadas no HTML nem no JSON-LD quando gmap_visible=false', function () {
    $p = Property::factory()->withoutMap()->create(['lat' => 38.7123456, 'lon' => -9.1398765]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('38.71234')
        ->and($html)->not->toContain('9.13987')
        ->and($html)->not->toContain('GeoCoordinates')
        ->and($html)->not->toContain('openstreetmap.org')
        ->and($html)->toContain(__('ui.property.map_hidden'));
});

it('com gmap_visible=true tem coordenadas no JSON-LD e o mapa só carrega ao clicar', function () {
    $p = Property::factory()->create(['lat' => 38.7123456, 'lon' => -9.1398765]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain('GeoCoordinates')
        ->and($html)->toContain('38.7123456')
        ->and($html)->toContain(__('ui.property.show_map'))
        // O iframe está dentro de <template x-if>: só existe no DOM depois do clique.
        ->and(preg_match('~<template x-if="show">\s*<iframe~', $html))->toBe(1);
});

it('um imóvel desativado responde 410 com semelhantes, e não 404', function () {
    $gone = Property::factory()->inactive()->create(['city' => 'Cascais']);
    $similar = Property::factory()->create(['city' => 'Cascais']);

    $this->get(route('property.show', $gone))
        ->assertStatus(410)
        ->assertSee(__('ui.property.gone_title'))
        ->assertSee($similar->slug)
        ->assertSee('noindex', false);
});

it('mostra imóveis semelhantes (mesma finalidade) e nunca o próprio', function () {
    $p = Property::factory()->create(['city' => 'Cascais']);
    $same = Property::factory()->create(['city' => 'Cascais']);
    $rent = Property::factory()->forRent()->create(['city' => 'Cascais']);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect(substr_count($html, 'data-slug="'.$same->slug.'"'))->toBe(1)
        ->and($html)->not->toContain('data-slug="'.$rent->slug.'"')
        ->and($html)->not->toContain('data-slug="'.$p->slug.'"');
});

/*
|--------------------------------------------------------------------------
| Zonas
|--------------------------------------------------------------------------
*/

it('as zonas derivam da carteira ativa e ligam a concelho e freguesia', function () {
    Property::factory()->count(2)->create(['city' => 'Cascais', 'locality' => 'Estoril']);
    Property::factory()->create(['city' => 'Cascais', 'locality' => 'Carcavelos']);
    Property::factory()->inactive()->create(['city' => 'Faro', 'locality' => 'Sé']);

    $index = $this->get(route('zones.index'))->assertOk()->getContent();
    expect($index)->toContain(route('zones.city', 'cascais'))->not->toContain('Faro');

    $city = $this->get(route('zones.city', 'cascais'))->assertOk()->getContent();
    expect($city)->toContain(route('zones.locality', ['cascais', 'estoril']))
        ->and(substr_count($city, 'data-slug='))->toBe(3);

    $this->get(route('zones.locality', ['cascais', 'estoril']))->assertOk()->assertSee('Estoril');
    $this->get(route('zones.city', 'faro'))->assertNotFound();
});

it('a página de zona usa o texto editorial quando existe', function () {
    Property::factory()->create(['city' => 'Cascais']);
    Zone::create(['city_slug' => 'cascais', 'title' => 'Viver em Cascais', 'intro' => 'Entre a serra e o mar.', 'body' => "Primeiro parágrafo.\n\nSegundo parágrafo."]);

    $this->get(route('zones.city', 'cascais'))->assertOk()
        ->assertSee('Viver em Cascais')
        ->assertSee('Entre a serra e o mar.')
        ->assertSee('Segundo parágrafo.');
});

/*
|--------------------------------------------------------------------------
| Favoritos
|--------------------------------------------------------------------------
*/

it('/favoritos renderiza os cartões pedidos por slug, só ativos, e ignora lixo', function () {
    $a = Property::factory()->create();
    $b = Property::factory()->inactive()->create();

    $html = $this->get(route('favorites', ['slugs' => "{$a->slug},{$b->slug},<script>,nao-existe"]))->assertOk()->getContent();

    // O slug do inativo pode aparecer no endereço do seletor de idioma (veio do
    // próprio pedido); o que não pode é haver cartão para ele.
    expect($html)->toContain('data-slug="'.$a->slug.'"')
        ->not->toContain('data-slug="'.$b->slug.'"');
    $this->get(route('favorites'))->assertOk()->assertSee(__('ui.favorites.empty'));
});

it('a página de favoritos poda os que já não existem no site', function () {
    // Um favorito de um imóvel vendido/retirado/apagado ficava preso no
    // browser para sempre: o coração contava-o e ele nunca saía. A página
    // passa a devolver ao Alpine a lista dos que o servidor confirmou, e o
    // resto é removido do localStorage.
    $ativo = Property::factory()->create();
    $retirado = Property::factory()->inactive()->create();

    $html = $this->get(route('favorites', ['slugs' => "{$ativo->slug},{$retirado->slug},fantasma-apagado"]))
        ->assertOk()
        ->getContent();

    // A chamada de poda vai com os slugs válidos — e só esses.
    expect($html)->toContain('$store.favorites.prune(')
        ->toContain($ativo->slug)
        ->not->toContain('data-slug="'.$retirado->slug.'"');
});

/*
|--------------------------------------------------------------------------
| SEO e cache
|--------------------------------------------------------------------------
*/

it('o sitemap inclui só imóveis ativos e as zonas', function () {
    $active = Property::factory()->create(['city' => 'Cascais', 'locality' => 'Estoril']);
    $inactive = Property::factory()->inactive()->create(['city' => 'Faro']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('property.show', $active))
        ->not->toContain($inactive->slug)
        ->toContain(route('zones.city', 'cascais'))
        ->toContain(route('zones.locality', ['cascais', 'estoril']))
        ->not->toContain('faro');
});

it('a cache das listagens é limpa quando o sync altera imóveis', function () {
    Property::factory()->create();
    $this->get(route('buy'))->assertOk();          // aquece a cache

    $new = Property::factory()->create();
    $this->get(route('buy'))->assertDontSee($new->slug);   // ainda em cache

    $r = new SyncResult(dryRun: false, force: false);
    $r->created = 1;
    event(new PropertiesSynced($r));

    $this->get(route('buy'))->assertSee($new->slug);        // cache invalidada
});

it('um sync sem alterações não esvazia a cache', function () {
    Property::factory()->create();
    PropertyCache::remember('sentinel', fn () => 'ok');

    event(new PropertiesSynced(new SyncResult(dryRun: false, force: false)));

    expect(PropertyCache::store()->get('props:sentinel'))->toBe('ok');
});
