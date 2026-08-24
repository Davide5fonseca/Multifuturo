<?php

/*
 * Fumo das páginas públicas da Fase 1: respondem, têm o layout, e o rodapé mostra
 * AMI e Livro de Reclamações.
 */

it('serve a homepage com título, canonical e rodapé legal', function () {
    config(['agency.ami' => '99999']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertSee(__('ui.footer.ami', ['number' => '99999']))
        ->assertSee(__('ui.footer.complaints_book'))
        ->assertSee(config('agency.complaints_book_url'), false);
});

it('avisa no rodapé quando o AMI ainda não está configurado', function () {
    config(['agency.ami' => null]);

    $this->get(route('home'))->assertOk()->assertSee(__('ui.footer.ami_missing'));
});

it('tem rotas separadas para comprar e arrendar', function () {
    $this->get(route('buy'))->assertOk()->assertSee(__('ui.listing.buy_title'));
    $this->get(route('rent'))->assertOk()->assertSee(__('ui.listing.rent_title'));
});

it('a 404 mostra a pesquisa de imóveis', function () {
    $this->get('/pagina-que-nao-existe')
        ->assertNotFound()
        ->assertSee(__('ui.errors.404_title'))
        ->assertSee('role="search"', false)
        ->assertSee(route('buy'), false);
});

it('não faz pedidos a fontes externas', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('fonts.gstatic.com')
        ->and($html)->toContain('/fonts/fraunces-latin.woff2');
});
