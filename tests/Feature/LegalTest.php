<?php

/*
 * Fase 6 — legal e conformidade: políticas, página institucional, banner de
 * cookies com consentimento granular, scripts bloqueados até opt-in.
 */

use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    config([
        'agency.name' => 'Multifuturo Imóveis Lda',
        'agency.ami' => '12345',
        'agency.address' => 'Rua Exemplo 1, 2750-000 Cascais',
        'agency.email' => 'geral@example.test',
        'agency.phone' => '+351 210 000 000',
    ]);
});

it('as páginas legais e institucional existem, são indexáveis e têm os dados da agência', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->toContain(__("legal.{$route}.title"))
        ->and($html)->toContain('AMI')
        ->and($html)->toContain('12345')                        // AMI substituído
        ->and($html)->toContain('geral@example.test')
        ->and($html)->not->toContain(':name')                  // nenhum placeholder por substituir
        ->and($html)->not->toContain(':email')
        ->and($html)->not->toContain(':ami')
        ->and($html)->toContain('<meta name="robots" content="index,follow">');
})->with(['privacy', 'terms', 'cookies', 'about']);

it('a política de privacidade mostra a versão em vigor (a mesma gravada nas leads)', function () {
    config(['agency.privacy_policy_version' => '2026-08-18']);

    $this->get(route('privacy'))->assertOk()->assertSee('Versão 2026-08-18');
});

it('a política de cookies descreve o cookie de consentimento e os favoritos locais', function () {
    config(['consent.cookie' => 'mf_consent']);

    $this->get(route('cookies'))->assertOk()
        ->assertSee('mf_consent')
        ->assertSee('multifuturo:favoritos')
        ->assertSee('OpenStreetMap');
});

it('o rodapé liga às políticas, ao Livro de Reclamações e a "Gerir cookies"', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain(route('privacy'))
        ->and($html)->toContain(route('terms'))
        ->and($html)->toContain(route('cookies'))
        ->and($html)->toContain('livroreclamacoes.pt')
        ->and($html)->toContain(__('legal.consent.manage'));
});

it('o banner de cookies existe em todas as páginas, com recusa efetiva e personalização', function () {
    foreach (['home', 'buy', 'contact'] as $route) {
        $html = $this->get(route($route))->assertOk()->getContent();

        expect($html)->toContain('id="consent-title"')
            ->and($html)->toContain(__('legal.consent.reject_all'))
            ->and($html)->toContain(__('legal.consent.accept_all'))
            ->and($html)->toContain(__('legal.consent.customize'))
            ->and($html)->toContain('window.MF_CONSENT')
            ->and($html)->toContain('"cookie":"'.config('consent.cookie').'"');
    }
});

it('nenhuma página carrega scripts de terceiros antes do consentimento', function () {
    foreach (['home', 'buy', 'rent', 'contact', 'valuation', 'privacy', 'zones.index'] as $route) {
        $html = $this->get(route($route))->assertOk()->getContent();

        preg_match_all('~<script[^>]+src="([^"]+)"~', $html, $m);
        foreach ($m[1] as $src) {
            expect(str_starts_with($src, 'http') && ! str_starts_with($src, config('app.url')))->toBeFalse("script externo em {$route}: {$src}");
        }
        expect($html)->not->toContain('googletagmanager')->not->toContain('google-analytics')->not->toContain('facebook.net');
    }
});

it('<x-consent-script> renderiza como text/plain e nunca como JavaScript executável', function () {
    $html = Blade::render('<x-consent-script category="analytics" src="https://stats.example.test/x.js" />');
    expect($html)->toContain('type="text/plain"')->toContain('data-consent="analytics"')->toContain('src="https://stats.example.test/x.js"');

    $inline = Blade::render('<x-consent-script category="marketing">console.log(1)</x-consent-script>');
    expect($inline)->toContain('type="text/plain"')->toContain('data-consent="marketing"')->toContain('console.log(1)');
});

it('trans_replace substitui placeholders longos antes dos curtos e usa travessão para vazios', function () {
    expect(trans_replace('A :name em :address (:a) — :email', ['name' => 'X', 'address' => 'Rua Y', 'a' => 'z', 'email' => null]))
        ->toBe('A X em Rua Y (z) — —');
});
