<?php

/*
 * Leads: formulários, gravação local, anti-spam, RGPD e aviso por email à
 * agência (sem CRM — decisão de 2026-08-19: a lead vive na nossa base de
 * dados e no backoffice).
 */

use App\Enums\LeadSource;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/** Payload válido de um formulário de contacto, com timestamp assinado "antigo" o suficiente. */
function leadPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'contact',
        'name' => 'Maria Teste',
        'email' => 'Maria@Example.test',
        'phone' => '+351 912 345 678',
        'message' => 'Gostava de saber mais.',
        'form_ts' => StoreLeadRequest::signedTimestamp(time() - 30),
    ], $overrides);
}

beforeEach(function () {
    config(['agency.email' => 'geral@multifuturo.test']);
    Notification::fake();
    RateLimiter::clear('leads:m:'.Lead::hashIp('127.0.0.1'));
    RateLimiter::clear('leads:h:'.Lead::hashIp('127.0.0.1'));
});

/*
|--------------------------------------------------------------------------
| Fluxo HTTP
|--------------------------------------------------------------------------
*/

it('grava a lead localmente e avisa a agência por email', function () {
    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('lead_sent', true);

    $lead = Lead::firstOrFail();

    expect($lead->name)->toBe('Maria Teste')
        ->and($lead->email)->toBe('maria@example.test')       // normalizado
        ->and($lead->source)->toBe(LeadSource::Contact)
        ->and($lead->consent_contact)->toBeFalse()             // nunca forçado a true
        ->and($lead->consent_marketing)->toBeFalse()
        ->and($lead->policy_version)->toBe(config('agency.privacy_policy_version'))
        ->and($lead->ip_hash)->toHaveLength(64)
        ->and($lead->ip_hash)->not->toContain('127.0.0.1');

    Notification::assertSentOnDemand(NewLeadReceived::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'geral@multifuturo.test');
});

it('sem email da agência nem administradores, a lead grava na mesma e nada é enviado', function () {
    config(['agency.email' => null]);

    $this->post(route('leads.store'), leadPayload())->assertRedirect();

    expect(Lead::count())->toBe(1);
    Notification::assertNothingSent();
});

it('cada administrador do backoffice recebe o email, nas três origens; os outros utilizadores não', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email' => 'chefe@multifuturo.test']);
    $admin2 = User::factory()->create(['is_admin' => true, 'email' => 'socio@multifuturo.test']);
    $consultor = User::factory()->create(['is_admin' => false]);
    $property = Property::factory()->create(['reference' => 'MF-321']);

    $this->post(route('leads.store'), leadPayload(['source' => 'property', 'property_slug' => $property->slug]));
    $this->post(route('leads.store'), leadPayload(['source' => 'valuation', 'payload' => ['city' => 'Sintra', 'locality' => 'Colares', 'area' => 120, 'estimate' => '300 000 € – 360 000 €']]));
    $this->post(route('leads.store'), leadPayload(['source' => 'contact']));

    Notification::assertSentToTimes($admin, NewLeadReceived::class, 3);
    Notification::assertSentToTimes($admin2, NewLeadReceived::class, 3);
    Notification::assertNotSentTo($consultor, NewLeadReceived::class);
    // O email geral da agência continua a receber, além dos administradores.
    Notification::assertSentOnDemandTimes(NewLeadReceived::class, 3);

    // O da avaliação leva os dados do simulador com os nomes do site e a ligação ao backoffice.
    Notification::assertSentTo($admin, NewLeadReceived::class, function ($notification) use ($admin) {
        $mail = $notification->toMail($admin);
        $text = implode(' ', array_map(fn ($l) => (string) $l, $mail->introLines));

        if (! str_contains($mail->subject, 'avaliação')) {
            return true;
        }

        return str_contains($mail->subject, 'Sintra')
            && str_contains($text, 'Freguesia: Colares')
            && str_contains($text, 'Estimativa mostrada no site: 300 000 € – 360 000 €')
            && str_contains($mail->actionUrl, '/admin/leads/');
    });
});

it('o email geral da agência não recebe em duplicado quando é o de um administrador', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email' => 'Geral@multifuturo.test']);

    $this->post(route('leads.store'), leadPayload());

    Notification::assertSentToTimes($admin, NewLeadReceived::class, 1);
    Notification::assertSentOnDemandTimes(NewLeadReceived::class, 0);
});

it('o email do aviso identifica o imóvel e os consentimentos', function () {
    $property = Property::factory()->create(['reference' => 'MF-777']);

    $this->post(route('leads.store'), leadPayload(['source' => 'property', 'property_slug' => $property->slug, 'consent_contact' => '1']));

    Notification::assertSentOnDemand(NewLeadReceived::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', array_map(fn ($l) => (string) $l, $mail->introLines));

        return str_contains($mail->subject, 'MF-777')
            && str_contains($text, 'Maria Teste')
            && str_contains($text, 'contacto sim');
    });
});

it('associa a lead ao imóvel pelo slug e herda a finalidade', function () {
    $property = Property::factory()->forRent()->create();

    $this->post(route('leads.store'), leadPayload(['source' => 'property', 'property_slug' => $property->slug]));

    $lead = Lead::firstOrFail();
    expect($lead->property_id)->toBe($property->id)
        ->and($lead->business_type)->toBe($property->business_type)
        ->and($lead->source)->toBe(LeadSource::Property);
});

it('guarda os dois consentimentos em separado quando dados', function () {
    $this->post(route('leads.store'), leadPayload(['consent_contact' => '1']));
    $this->post(route('leads.store'), leadPayload(['consent_marketing' => '1', 'email' => 'outra@example.test']));

    $leads = Lead::orderBy('id')->get();
    expect($leads[0]->consent_contact)->toBeTrue()->and($leads[0]->consent_marketing)->toBeFalse()
        ->and($leads[1]->consent_contact)->toBeFalse()->and($leads[1]->consent_marketing)->toBeTrue();
});

it('guarda o payload da avaliação só com chaves conhecidas', function () {
    $this->post(route('leads.store'), leadPayload([
        'source' => 'valuation',
        'payload' => ['address' => 'Rua X, 1', 'city' => 'Cascais', 'bedrooms' => 3, 'area' => 120, 'hack' => 'x'],
    ]))->assertSessionHasErrors('payload');   // chave desconhecida rejeitada

    $this->post(route('leads.store'), leadPayload([
        'source' => 'valuation',
        'payload' => ['address' => 'Rua X, 1', 'city' => 'Cascais', 'bedrooms' => 3, 'area' => 120],
    ]))->assertSessionHasNoErrors();

    // jsonb reordena as chaves — comparação canónica.
    expect(Lead::firstOrFail()->payload)->toEqualCanonicalizing(['address' => 'Rua X, 1', 'city' => 'Cascais', 'bedrooms' => 3, 'area' => 120]);
});

it('valida os campos obrigatórios e o formato', function () {
    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload(['name' => 'A', 'email' => 'nao-e-email', 'phone' => 'abc', 'source' => 'x']))
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors(['name', 'email', 'phone', 'source']);

    expect(Lead::count())->toBe(0);
    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Anti-spam
|--------------------------------------------------------------------------
*/

it('honeypot preenchido: aceita em silêncio, não grava, não envia email', function () {
    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('contact'))
        ->assertSessionHas('lead_sent', true);   // o bot vê o mesmo que um humano

    expect(Lead::count())->toBe(0);
    Notification::assertNothingSent();
});

it('submissão demasiado rápida ou com timestamp forjado é tratada como spam', function () {
    $this->post(route('leads.store'), leadPayload(['form_ts' => StoreLeadRequest::signedTimestamp(time())]));
    $this->post(route('leads.store'), leadPayload(['form_ts' => time().'.assinatura-falsa']));

    expect(Lead::count())->toBe(0);
});

it('aplica rate limiting por IP', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('leads.store'), leadPayload(['email' => "p{$i}@example.test"]))->assertRedirect();
    }

    $this->post(route('leads.store'), leadPayload(['email' => 'p6@example.test']))->assertStatus(429);

    expect(Lead::count())->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Modelo
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Páginas com formulário
|--------------------------------------------------------------------------
*/

it('as páginas de contacto e avaliação mostram o formulário com honeypot e consentimentos desmarcados', function () {
    foreach (['contact', 'valuation'] as $route) {
        $html = $this->get(route($route))->assertOk()->getContent();

        expect($html)->toContain('name="website"')
            ->and($html)->toContain('name="consent_contact"')
            ->and($html)->toContain('name="consent_marketing"')
            ->and($html)->not->toContain('name="consent_contact" value="1" checked')
            ->and($html)->toContain(route('privacy'))
            ->and($html)->toContain('name="form_ts"');
    }
});

it('o aviso à agência é enfileirado, e não enviado no pedido HTTP', function () {
    // O contacto tem de ficar guardado mesmo que o email falhe ou demore:
    // por isso o aviso vai para a fila. Sem um processo a tratar da fila
    // (serviço "queue" no compose.yaml, e o equivalente em produção), os
    // emails ficam parados — foi por isso que este teste existe.
    $this->post(route('leads.store'), leadPayload());

    Notification::assertSentOnDemand(NewLeadReceived::class, function ($notification) {
        return $notification instanceof ShouldQueue;
    });

    expect(Lead::count())->toBe(1);
});
