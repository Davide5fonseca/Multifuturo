<?php

/*
 * Backoffice (/admin, Filament): autenticação obrigatória e recursos acessíveis
 * a utilizadores autenticados.
 */

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Route;

it('o login do backoffice é o do portal (/entrar); o do Filament já não existe', function () {
    $this->get('/entrar')->assertOk()->assertSee('Entrar');
    $this->get('/admin/login')->assertNotFound();
});

it('visitantes não autenticados são redirecionados para o login do portal', function (string $path) {
    $this->get($path)->assertRedirect('/entrar');
})->with(['/admin', '/admin/properties', '/admin/leads', '/admin/zones']);

it('um utilizador autenticado vê o painel e as listagens', function () {
    $user = User::factory()->create();
    Property::factory()->create(['reference' => 'MF-901', 'city' => 'Cascais']);
    Lead::factory()->create(['name' => 'Pedido Teste']);

    $this->actingAs($user)->get('/admin')->assertOk();
    $this->actingAs($user)->get('/admin/properties')->assertOk()->assertSee('MF-901');
    $this->actingAs($user)->get('/admin/leads')->assertOk()->assertSee('Pedido Teste');
    $this->actingAs($user)->get('/admin/zones')->assertOk();
});

it('não é possível criar pedidos à mão no backoffice', function () {
    // As leads nascem dos formulários públicos: não há rota nem botão de criação.
    expect(LeadResource::canCreate())->toBeFalse()
        ->and(Route::has('filament.admin.resources.leads.create'))->toBeFalse();
});

it('o backoffice não é indexável (fora do sitemap; robots dinâmico bloqueia-o em produção via Disallow /admin?)', function () {
    // O sitemap nunca inclui /admin; o robots.txt de produção só permite o site público.
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->not->toContain('/admin');
});

it('o backoffice está todo em português', function () {
    $this->actingAs(User::factory()->create());

    // O idioma por omissão não depende do .env: uma instalação nova arranca em pt.
    expect(config('app.locale'))->toBe('pt')
        ->and(config('app.fallback_locale'))->toBe('pt');

    $contacto = Contact::factory()->create();
    $evento = Event::factory()->create();

    $this->get(route('filament.admin.resources.contacts.index'))->assertOk()
        ->assertSee('Nome')->assertSee('Telefone')->assertSee('Responsável')
        ->assertDontSee('Email address')->assertDontSee('Created at');

    $this->get(route('filament.admin.resources.events.index'))->assertOk()
        ->assertSee('Título')->assertSee('Início')->assertSee('Concluído')
        ->assertDontSee('Starts at')->assertDontSee('Is done');

    $this->get(route('filament.admin.resources.contacts.edit', $contacto))->assertOk()
        ->assertSee('Preferências')->assertDontSee('Email address');

    $this->get(route('filament.admin.resources.events.edit', $evento))->assertOk()
        ->assertSee('Ligações')->assertDontSee('Ends at');
});
