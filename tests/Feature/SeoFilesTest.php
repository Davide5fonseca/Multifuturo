<?php

use App\Support\AppUrl;

/*
 * sitemap.xml e robots.txt são dinâmicos e derivam de config('app.url').
 */

it('gera o sitemap a partir de app.url', function () {
    config(['app.url' => 'https://exemplo-multifuturo.pt']);
    AppUrl::forceFromConfig();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>https://exemplo-multifuturo.pt/comprar</loc>', false)
        ->assertSee('<loc>https://exemplo-multifuturo.pt/arrendar</loc>', false)
        ->assertDontSee('multifuturo.test');
});

it('bloqueia os robots fora de produção', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /');
});

it('em produção permite indexação e aponta para o sitemap em app.url', function () {
    config(['app.url' => 'https://exemplo-multifuturo.pt']);
    AppUrl::forceFromConfig();
    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Allow: /')
        ->assertSee('Sitemap: https://exemplo-multifuturo.pt/sitemap.xml');
});
