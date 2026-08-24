<?php

/*
 * Módulos de CRM do backoffice: dashboard (leads por tipo, histórico,
 * agenda, visualizações), registo automático de alterações e contagem de
 * visualizações das fichas.
 */

use App\Enums\EventType;
use App\Enums\LeadKind;
use App\Enums\LeadPriority;
use App\Enums\LeadStage;
use App\Filament\Widgets\BuyerLeadsWidget;
use App\Filament\Widgets\ListingLeadsWidget;
use App\Filament\Widgets\PropertyActivitiesWidget;
use App\Filament\Widgets\PropertyViewsChart;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\PropertyView;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('separa leads de angariação e de compradores nos dois quadros', function () {
    $angariacao = Lead::factory()->create(['kind' => LeadKind::Listing, 'status' => LeadStage::Prospecting, 'name' => 'Dono Silva']);
    $comprador = Lead::factory()->create(['kind' => LeadKind::Buyer, 'status' => LeadStage::Received, 'name' => 'Comprador Costa']);

    Livewire::test(ListingLeadsWidget::class)
        ->assertCanSeeTableRecords([$angariacao])
        ->assertCanNotSeeTableRecords([$comprador]);

    Livewire::test(BuyerLeadsWidget::class)
        ->assertCanSeeTableRecords([$comprador])
        ->assertCanNotSeeTableRecords([$angariacao]);
});

it('os quadros só mostram leads em aberto', function () {
    $aberta = Lead::factory()->create(['kind' => LeadKind::Buyer, 'status' => LeadStage::Visit]);
    $fechada = Lead::factory()->create(['kind' => LeadKind::Buyer, 'status' => LeadStage::Closed]);
    $perdida = Lead::factory()->create(['kind' => LeadKind::Buyer, 'status' => LeadStage::Lost]);

    Livewire::test(BuyerLeadsWidget::class)
        ->assertCanSeeTableRecords([$aberta])
        ->assertCanNotSeeTableRecords([$fechada, $perdida]);
});

it('a agenda mostra o que está por fazer e permite concluir', function () {
    $porFazer = Event::factory()->create(['title' => 'Telefonar ao cliente', 'is_done' => false]);
    $feito = Event::factory()->create(['title' => 'Já tratado', 'is_done' => true]);

    Livewire::test(UpcomingEventsWidget::class)
        ->assertCanSeeTableRecords([$porFazer])
        ->assertCanNotSeeTableRecords([$feito])
        ->callTableAction('concluir', $porFazer);

    expect($porFazer->refresh()->is_done)->toBeTrue();
});

it('o quadro de actualizações mostra o histórico mais recente primeiro', function () {
    $p = Property::factory()->create();
    $antiga = PropertyActivity::create(['property_id' => $p->id, 'type' => 'created', 'created_at' => now()->subDays(3)]);
    $recente = PropertyActivity::create(['property_id' => $p->id, 'type' => 'price', 'detail' => '100 € → 90 €', 'created_at' => now()]);

    Livewire::test(PropertyActivitiesWidget::class)
        ->assertCanSeeTableRecords([$recente, $antiga], inOrder: true);
});

it('o gráfico soma as visualizações por dia', function () {
    $a = Property::factory()->create();
    $b = Property::factory()->create();
    PropertyView::create(['property_id' => $a->id, 'viewed_on' => today(), 'views' => 4]);
    PropertyView::create(['property_id' => $b->id, 'viewed_on' => today(), 'views' => 6]);
    PropertyView::create(['property_id' => $a->id, 'viewed_on' => today()->subDays(60), 'views' => 99]); // fora da janela

    $data = invade(new PropertyViewsChart)->getData();

    expect(end($data['datasets'][0]['data']))->toBe(10)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(10);
});

/*
|--------------------------------------------------------------------------
| Histórico automático
|--------------------------------------------------------------------------
*/

it('regista a criação, a alteração de preço e as mudanças de estado', function () {
    $p = Property::factory()->create(['price' => 520001]);
    expect($p->activities()->where('type', 'created')->exists())->toBeTrue();

    $p->update(['price' => 520000]);
    $preco = $p->activities()->where('type', 'price')->firstOrFail();
    expect($preco->detail)->toContain('520')->toContain('→');

    $p->update(['is_sold' => true]);
    expect($p->activities()->where('type', 'status')->where('detail', 'Vendida')->exists())->toBeTrue();

    $p->update(['translations' => ['pt' => ['title' => 'Outro título']]]);
    expect($p->activities()->where('type', 'updated')->exists())->toBeTrue();
});

it('o histórico guarda quem fez a alteração', function () {
    $user = User::factory()->create(['name' => 'Ana Consultora']);
    $this->actingAs($user);

    $p = Property::factory()->create();

    expect($p->activities()->first()->user_id)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Visualizações
|--------------------------------------------------------------------------
*/

it('ver a ficha no site conta uma visualização, sem guardar dados do visitante', function () {
    $p = Property::factory()->create();

    $this->get(route('property.show', $p))->assertOk();
    $this->get(route('property.show', $p))->assertOk();

    $view = PropertyView::where('property_id', $p->id)->whereDate('viewed_on', today())->firstOrFail();

    expect($view->views)->toBe(2)
        ->and(collect($view->getAttributes())->keys())
        ->not->toContain('ip', 'ip_hash', 'user_agent', 'session_id');
});

it('uma ficha indisponível (410) não conta visualização', function () {
    $p = Property::factory()->create(['is_sold' => true]);

    $this->get(route('property.show', $p))->assertStatus(410);

    expect(PropertyView::where('property_id', $p->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Clientes
|--------------------------------------------------------------------------
*/

it('um cliente agrega as suas leads e eventos', function () {
    $contact = Contact::factory()->create(['name' => 'Sara Lopes']);
    Lead::factory()->count(2)->create(['contact_id' => $contact->id]);
    Event::factory()->create(['contact_id' => $contact->id, 'type' => EventType::Visit]);

    expect($contact->leads)->toHaveCount(2)
        ->and($contact->events)->toHaveCount(1)
        ->and($contact->events->first()->type)->toBe(EventType::Visit);
});

it('as leads têm pipeline próprio por tipo', function () {
    expect(LeadStage::forKind(LeadKind::Listing))->toHaveKeys(['prospecting', 'contact_owner', 'valuation', 'listed', 'lost'])
        ->and(LeadStage::forKind(LeadKind::Buyer))->toHaveKeys(['received', 'qualification', 'visit', 'proposal', 'closed', 'lost'])
        ->and(LeadPriority::Urgent->label())->toBe('Urgente');
});

it('apagar um imóvel não rebenta e deixa registo de quem o apagou', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $p = Property::factory()->create(['reference' => 'REF-APAGAR']);
    expect(PropertyActivity::count())->toBe(1); // "Nova"

    $p->delete();

    // O histórico do imóvel morre com ele (chave estrangeira em cascata);
    // fica só a linha da eliminação, sem imóvel e com a referência no detalhe.
    $registo = PropertyActivity::query()->latest('id')->first();

    expect(PropertyActivity::count())->toBe(1)
        ->and($registo->type)->toBe('deleted')
        ->and($registo->property_id)->toBeNull()
        ->and($registo->user_id)->toBe($user->id)
        ->and($registo->detail)->toContain('REF-APAGAR');
});

it('o quadro de actualizações mostra a linha de um imóvel apagado', function () {
    $apagado = PropertyActivity::create(['type' => 'deleted', 'detail' => 'REF-APAGAR — Moradia']);

    Livewire::test(PropertyActivitiesWidget::class)
        ->assertCanSeeTableRecords([$apagado]);
});

it('a agenda aceita os doze tipos de evento do CRM', function () {
    expect(EventType::cases())->toHaveCount(12)
        ->and(EventType::Deed->label())->toBe('Escritura')
        ->and(EventType::Cpcv->label())->toBe('CPCV')
        ->and(EventType::ServiceDay->label())->toBe('Dia de serviço');

    // Os tipos novos passam na restrição CHECK da base de dados.
    foreach (['email', 'deed', 'other', 'arrival', 'cpcv', 'service_day', 'offer'] as $type) {
        $event = Event::factory()->create(['type' => $type]);

        expect($event->fresh()->type->value)->toBe($type);
    }
});
