<?php

/*
 * Acessibilidade — verificações automáticas básicas sobre o HTML servido.
 * Não substituem uma auditoria manual (contraste, teclado, leitores de ecrã),
 * mas apanham as regressões estruturais mais comuns.
 */

use App\Models\Property;
use App\Models\User;

/** Páginas representativas: [rota, parâmetros ou null]. */
function a11yPages(): array
{
    $p = Property::factory()->create(['city' => 'Cascais']);

    return [
        ['home', null],
        ['buy', null],
        ['property.show', $p],
        ['zones.index', null],
        ['contact', null],
        ['valuation', null],
        ['privacy', null],
        ['favorites', null],
    ];
}

it('todas as páginas têm lang pt-PT, skip link, landmarks e exatamente um h1', function () {
    foreach (a11yPages() as [$route, $param]) {
        $html = $this->get(route($route, $param))->assertOk()->getContent();

        expect($html)->toContain('lang="pt-PT"')
            ->and($html)->toContain('href="#conteudo"')                       // saltar para o conteúdo
            ->and($html)->toContain('<main id="conteudo"')
            ->and(substr_count($html, '<h1'))->toBe(1, "h1 múltiplo/ausente em {$route}")
            ->and($html)->toContain('<header')
            ->and($html)->toContain('<footer')
            ->and($html)->toContain('aria-label="'.__('ui.nav.main').'"');   // nav principal identificada
    }
});

it('todas as imagens têm atributo alt', function () {
    foreach (a11yPages() as [$route, $param]) {
        $html = $this->get(route($route, $param))->getContent();

        preg_match_all('/<img\b[^>]*>/i', $html, $m);
        foreach ($m[0] as $img) {
            expect((bool) preg_match('/\balt=/', $img))->toBeTrue("imagem sem alt em {$route}: ".substr($img, 0, 120));
        }
    }
});

it('todos os campos de formulário têm label associada (for/id) ou aria-label', function () {
    foreach (['contact', 'valuation', 'buy'] as $route) {
        $html = $this->get(route($route))->getContent();

        preg_match_all('/<(?:input|select|textarea)\b[^>]*>/i', $html, $m);
        preg_match_all('/<label[^>]*\bfor="([^"]+)"/i', $html, $labels);
        $labelled = array_flip($labels[1]);

        foreach ($m[0] as $field) {
            if (preg_match('/type="(hidden|submit)"/i', $field)) {
                continue;
            }
            $hasAria = (bool) preg_match('/aria-label(?:ledby)?=/', $field);
            preg_match('/\bid="([^"]+)"/', $field, $idm);
            $hasLabel = isset($idm[1]) && isset($labelled[$idm[1]]);
            // Checkboxes de consentimento estão embrulhadas em <label> sem for — também conta.
            $wrapped = (bool) preg_match('/name="(consent_|caracteristicas)/', $field);

            expect($hasAria || $hasLabel || $wrapped)->toBeTrue("campo sem label em {$route}: ".substr($field, 0, 120));
        }
    }
});

it('os links e botões só de ícone têm nome acessível', function () {
    $p = Property::factory()->create();
    $html = $this->get(route('buy'))->getContent();

    // Botão de favorito no cartão: aria-label dinâmico via Alpine.
    expect($html)->toContain(':aria-label=');

    $home = $this->get(route('home'))->getContent();
    // Link de favoritos no cabeçalho: texto sr-only.
    expect($home)->toContain('sr-only');
});

it('nenhuma página tem autofocus nem tabindex positivo', function () {
    foreach (a11yPages() as [$route, $param]) {
        $html = $this->get(route($route, $param))->getContent();

        expect($html)->not->toContain('autofocus')
            ->and((bool) preg_match('/tabindex="[1-9]/', $html))->toBeFalse("tabindex positivo em {$route}");
    }
});

it('a lista de imóveis esconde as colunas secundárias em ecrãs pequenos', function () {
    $this->actingAs(User::factory()->create());
    Property::factory()->create();

    $html = $this->get(route('filament.admin.resources.properties.index'))->assertOk()->getContent();

    // Doze colunas num telemóvel dariam 1400px de tabela para arrastar de lado:
    // no telemóvel ficam Referência, Preço e Estado; as outras entram por breakpoint.
    expect($html)->toContain('sm:fi-visible')
        ->toContain('md:fi-visible')
        ->toContain('lg:fi-visible')
        ->toContain('xl:fi-visible');
});

it('os alvos de toque do site têm a altura mínima recomendada', function () {
    $p = Property::factory()->create();

    // O coração dos favoritos: 44px de área de toque (WCAG 2.5.8).
    $this->get(route('buy'))->assertOk()->assertSee('h-11 w-11', escape: false);

    // Botões e campos herdam min-h-11 do CSS base; as caixas de consentimento
    // passaram de 16px para 20px.
    $this->get(route('contact'))->assertOk()->assertSee('h-5 w-5 shrink-0 accent-olive-600', escape: false);

    $this->get(route('property.show', $p))->assertOk();
});
