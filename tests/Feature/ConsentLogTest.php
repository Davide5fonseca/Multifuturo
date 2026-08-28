<?php

/*
 * Prova do consentimento de cookies: cada escolha do aviso é registada no
 * servidor (sem identificar ninguém) e apagada ao fim de 24 meses.
 */

use App\Models\ConsentLog;
use App\Models\Lead;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('leads:m:'.Lead::hashIp('127.0.0.1'));
    RateLimiter::clear('leads:h:'.Lead::hashIp('127.0.0.1'));
});

it('regista a escolha com a versão, as categorias, o idioma e o IP em hash — nunca o IP', function () {
    $this->postJson(route('consent.store'), [
        'version' => 1,
        'action' => 'custom',
        'choices' => ['analytics' => true, 'marketing' => false],
    ])->assertNoContent();

    $log = ConsentLog::first();

    expect($log->version)->toBe(1)
        ->and($log->action)->toBe('custom')
        ->and($log->choices)->toBe(['analytics' => true, 'marketing' => false])
        ->and($log->locale)->toBe('pt')
        ->and($log->ip_hash)->toHaveLength(64)
        ->and($log->ip_hash)->not->toContain('127.0.0.1')
        ->and($log->created_at)->not->toBeNull();
});

it('rejeita pedidos mal formados: categoria desconhecida, ação inválida, versão em falta', function () {
    $this->postJson(route('consent.store'), ['version' => 1, 'action' => 'accept_all', 'choices' => ['analytics' => true, 'marketing' => true, 'tracking' => true]])
        ->assertUnprocessable();
    $this->postJson(route('consent.store'), ['version' => 1, 'action' => 'tudo', 'choices' => ['analytics' => true, 'marketing' => true]])
        ->assertUnprocessable();
    $this->postJson(route('consent.store'), ['action' => 'reject_all', 'choices' => ['analytics' => false, 'marketing' => false]])
        ->assertUnprocessable();

    expect(ConsentLog::count())->toBe(0);
});

it('os registos com mais de 24 meses são apagados pelo model:prune; os recentes ficam', function () {
    ConsentLog::create(['version' => 1, 'action' => 'reject_all', 'choices' => ['analytics' => false, 'marketing' => false], 'created_at' => now()->subMonths(25)]);
    ConsentLog::create(['version' => 1, 'action' => 'accept_all', 'choices' => ['analytics' => true, 'marketing' => true], 'created_at' => now()->subMonths(23)]);

    $this->artisan('model:prune', ['--model' => [ConsentLog::class]])->assertSuccessful();

    expect(ConsentLog::count())->toBe(1)
        ->and(ConsentLog::first()->action)->toBe('accept_all');
});

it('o aviso de cookies sabe para onde enviar o registo, e a política explica-o', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('"endpoint":"'.str_replace('/', '\/', route('consent.store')).'"', false);

    $this->get(route('cookies'))->assertOk()
        ->assertSee('prova do consentimento')
        ->assertSee('24 meses');
});
