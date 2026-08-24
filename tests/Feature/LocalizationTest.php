<?php

/*
 * Site multilingue: prefixo de idioma no endereço, seletor, hreflang, sitemap
 * e recurso ao português quando falta tradução.
 */

use App\Models\Property;
use App\Support\Locales;

it('a raiz reencaminha para o idioma por omissão', function () {
    $this->get('/')->assertRedirect(route('home', ['locale' => Locales::default()]));
});

it('cada idioma tem o seu prefixo e o seu texto', function () {
    $this->get('/pt/comprar')->assertOk()
        ->assertSee('Imóveis para comprar')
        ->assertSee('Arrendar');

    $this->get('/en/comprar')->assertOk()
        ->assertSee('Properties for sale')
        ->assertSee('Rent');
});

it('um idioma desligado não tem rotas', function () {
    $this->get('/fr/comprar')->assertNotFound();
    $this->get('/de')->assertNotFound();
});

it('as ligações da página seguem o idioma em que estamos', function () {
    // Nada nas views sabe de idiomas: os route() geram sozinhos o prefixo certo.
    $this->get('/en/comprar')->assertOk()
        ->assertSee('/en/contactos', escape: false)
        ->assertDontSee('"'.url('/pt/contactos').'"', escape: false);
});

it('o html lang, o hreflang e o x-default acompanham o idioma', function () {
    $this->get('/pt')->assertOk()
        ->assertSee('<html lang="pt-PT"', escape: false)
        ->assertSee('hreflang="en"', escape: false)
        ->assertSee('hreflang="x-default"', escape: false);

    $this->get('/en')->assertOk()
        ->assertSee('<html lang="en"', escape: false)
        ->assertSee('hreflang="pt-PT"', escape: false)
        ->assertSee('og:locale" content="en"', escape: false);
});

it('o seletor de idioma leva para a mesma página, com os filtros', function () {
    $this->get('/pt/comprar?q=espinho')->assertOk()
        ->assertSee('/en/comprar?q=espinho', escape: false);

    // Mas o hreflang aponta para a página base, como o canonical.
    $this->get('/pt/comprar?q=espinho')->assertOk()
        ->assertSee('hreflang="en" href="'.url('/en/comprar').'"', escape: false);
});

it('o seletor mantém-se na mesma ficha ao trocar de idioma', function () {
    $p = Property::factory()->create();

    $this->get(route('property.show', $p))->assertOk()
        ->assertSee('/en/imoveis/'.$p->slug, escape: false);
});

it('o sitemap lista as duas versões de cada página', function () {
    $p = Property::factory()->create();

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/pt/comprar').'</loc>', escape: false)
        ->assertSee('<loc>'.url('/en/comprar').'</loc>', escape: false)
        ->assertSee('<loc>'.url('/pt/imoveis/'.$p->slug).'</loc>', escape: false)
        ->assertSee('<loc>'.url('/en/imoveis/'.$p->slug).'</loc>', escape: false);
});

it('sem tradução, o texto recorre ao português em vez de mostrar a chave', function () {
    // legal.php só existe em pt: a página inglesa mostra o texto português,
    // nunca "legal.privacy.title".
    $this->get('/en/politica-de-privacidade')->assertOk()
        ->assertDontSee('legal.');
});

it('o texto do imóvel recorre ao português enquanto não houver tradução', function () {
    $p = Property::factory()->create([
        'translations' => ['pt' => ['title' => 'Moradia com vista', 'description' => 'Descrição em português.']],
    ]);

    $this->get('/en/imoveis/'.$p->slug)->assertOk()->assertSee('Moradia com vista');

    // Com tradução, é a inglesa que aparece.
    $p->update(['translations' => [
        'pt' => ['title' => 'Moradia com vista'],
        'en' => ['title' => 'House with a view'],
    ]]);

    $this->get('/en/imoveis/'.$p->slug)->assertOk()->assertSee('House with a view');
    $this->get('/pt/imoveis/'.$p->slug)->assertOk()->assertSee('Moradia com vista');
});

it('num site de um só idioma não há seletor nem hreflang', function () {
    config(['locales.enabled' => ['pt']]);

    expect(Locales::isMultilingual())->toBeFalse()
        ->and(Locales::alternates())->toBe([]);
});

it('o banner de cookies está traduzido, mesmo com as páginas legais em português', function () {
    $this->get('/pt')->assertOk()->assertSee('Cookies e privacidade')->assertSee('Aceitar tudo');
    $this->get('/en')->assertOk()->assertSee('Cookies and privacy')->assertSee('Accept all');

    // As páginas legais continuam em português (tradução por encomendar).
    $this->get('/en/politica-de-privacidade')->assertOk()->assertSee('Política de privacidade');
});
