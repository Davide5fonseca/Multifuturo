<?php

/*
 * Fumo do backoffice: abrir cada página de cada secção com um registo real.
 *
 * Existe por causa de um erro em produção — a ficha de um pedido rebentava ao
 * abrir ("Call to a member function format() on string"), porque nenhum teste
 * chegava a renderizar essa página. Listar não chega: é preciso abrir.
 */

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create(['is_admin' => true])));

it('todas as listagens do backoffice abrem', function () {
    Property::factory()->create();
    Lead::factory()->create();
    Contact::factory()->create();
    Event::factory()->create();

    foreach (['properties', 'leads', 'contacts', 'events', 'zones'] as $recurso) {
        $this->get(route("filament.admin.resources.{$recurso}.index"))
            ->assertOk();
    }

    $this->get(route('filament.admin.pages.dashboard'))->assertOk();
    $this->get(route('filament.admin.pages.calendario'))->assertOk();
});

it('todas as fichas do backoffice abrem com um registo real', function () {
    $fichas = [
        'properties' => Property::factory()->create(),
        'leads' => Lead::factory()->create(),
        'contacts' => Contact::factory()->create(),
        'events' => Event::factory()->create(),
    ];

    foreach ($fichas as $recurso => $registo) {
        $this->get(route("filament.admin.resources.{$recurso}.edit", $registo))
            ->assertOk();
    }
});

it('a ficha de um pedido mostra a data em português e na hora certa', function () {
    // 14:30 em Lisboa tem de aparecer como 14:30. O formulário entrega a data
    // em texto e em UTC; sem repor o fuso, o horário de verão mostrava 13:30.
    $lead = Lead::factory()->create(['created_at' => '2026-08-25 14:30:00']);

    Livewire::test(EditLead::class, ['record' => $lead->getKey()])
        ->assertFormSet(['created_at' => '25/08/2026 14:30']);
});

it('todos os formulários de criação abrem', function () {
    foreach (['properties', 'contacts', 'events', 'zones'] as $recurso) {
        $this->get(route("filament.admin.resources.{$recurso}.create"))
            ->assertOk();
    }
});
