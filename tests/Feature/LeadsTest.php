<?php

/*
 * Fase 5 — leads: formulários, gravação local, anti-spam, RGPD e job de envio ao CRM.
 */

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\SendLeadToCasafari;
use App\Models\Lead;
use App\Models\Property;
use App\Notifications\LeadDeliveryFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
    config([
        'casafari.token' => 'tok-teste',
        'casafari.customer_origin_id' => '42',
        'casafari.lead_url' => 'https://insert.example.test/lead',
        'casafari.alert_email' => 'alertas@example.test',
    ]);
    RateLimiter::clear('leads:m:'.Lead::hashIp('127.0.0.1'));
    RateLimiter::clear('leads:h:'.Lead::hashIp('127.0.0.1'));
});

/*
|--------------------------------------------------------------------------
| Fluxo HTTP
|--------------------------------------------------------------------------
*/

it('grava a lead localmente e coloca o envio na queue', function () {
    Queue::fake();

    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('lead_sent', true);

    $lead = Lead::firstOrFail();

    expect($lead->name)->toBe('Maria Teste')
        ->and($lead->email)->toBe('maria@example.test')       // normalizado
        ->and($lead->source)->toBe(LeadSource::Contact)
        ->and($lead->crm_status)->toBe(LeadStatus::Pending)
        ->and($lead->consent_contact)->toBeFalse()             // nunca forçado a true
        ->and($lead->consent_marketing)->toBeFalse()
        ->and($lead->policy_version)->toBe(config('agency.privacy_policy_version'))
        ->and($lead->ip_hash)->toHaveLength(64)
        ->and($lead->ip_hash)->not->toContain('127.0.0.1');

    Queue::assertPushed(SendLeadToCasafari::class, fn ($job) => $job->leadId === $lead->id);
});

it('a lead grava localmente mesmo quando o CRM está em baixo', function () {
    // Queue síncrona + CRM a devolver 500: o job falha, mas a lead JÁ está na base de dados.
    config(['queue.default' => 'sync']);
    Http::fake(['insert.example.test/*' => Http::response('down', 500)]);

    try {
        $this->post(route('leads.store'), leadPayload());
    } catch (Throwable) {
        // com queue sync a exceção do job propaga; em produção a queue é assíncrona
    }

    // Com o driver sync o job falha de imediato (failed) — em produção fica pending e entra em retry.
    expect(Lead::count())->toBe(1)
        ->and(Lead::first()->crm_status)->not->toBe(LeadStatus::Sent)
        ->and(Lead::first()->attempts)->toBe(1);
});

it('associa a lead ao imóvel pelo slug e herda a finalidade', function () {
    Queue::fake();
    $property = Property::factory()->forRent()->create();

    $this->post(route('leads.store'), leadPayload(['source' => 'property', 'property_slug' => $property->slug]));

    $lead = Lead::firstOrFail();
    expect($lead->property_id)->toBe($property->id)
        ->and($lead->business_type)->toBe($property->business_type)
        ->and($lead->source)->toBe(LeadSource::Property);
});

it('guarda os dois consentimentos em separado quando dados', function () {
    Queue::fake();

    $this->post(route('leads.store'), leadPayload(['consent_contact' => '1']));
    $this->post(route('leads.store'), leadPayload(['consent_marketing' => '1', 'email' => 'outra@example.test']));

    $leads = Lead::orderBy('id')->get();
    expect($leads[0]->consent_contact)->toBeTrue()->and($leads[0]->consent_marketing)->toBeFalse()
        ->and($leads[1]->consent_contact)->toBeFalse()->and($leads[1]->consent_marketing)->toBeTrue();
});

it('guarda o payload da avaliação só com chaves conhecidas', function () {
    Queue::fake();

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
    Queue::fake();

    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload(['name' => 'A', 'email' => 'nao-e-email', 'phone' => 'abc', 'source' => 'x']))
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors(['name', 'email', 'phone', 'source']);

    expect(Lead::count())->toBe(0);
    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| Anti-spam
|--------------------------------------------------------------------------
*/

it('honeypot preenchido: aceita em silêncio e não grava', function () {
    Queue::fake();

    $this->from(route('contact'))
        ->post(route('leads.store'), leadPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('contact'))
        ->assertSessionHas('lead_sent', true);   // o bot vê o mesmo que um humano

    expect(Lead::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('submissão demasiado rápida ou com timestamp forjado é tratada como spam', function () {
    Queue::fake();

    $this->post(route('leads.store'), leadPayload(['form_ts' => StoreLeadRequest::signedTimestamp(time())]));
    $this->post(route('leads.store'), leadPayload(['form_ts' => time().'.assinatura-falsa']));

    expect(Lead::count())->toBe(0);
});

it('aplica rate limiting por IP', function () {
    Queue::fake();

    foreach (range(1, 5) as $i) {
        $this->post(route('leads.store'), leadPayload(['email' => "p{$i}@example.test"]))->assertRedirect();
    }

    $this->post(route('leads.store'), leadPayload(['email' => 'p6@example.test']))->assertStatus(429);

    expect(Lead::count())->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Job de envio ao CRM
|--------------------------------------------------------------------------
*/

it('envia a lead ao CRM com os campos esperados e marca sent', function () {
    $property = Property::factory()->create(['internal_id' => '777', 'reference' => 'MF-777']);
    $lead = Lead::factory()->create([
        'source' => LeadSource::Property,
        'property_id' => $property->id,
        'consent_contact' => true,
        'consent_marketing' => false,
    ]);

    Http::fake(['insert.example.test/*' => Http::response(['status' => true, 'id' => 'L-1'], 200)]);

    (new SendLeadToCasafari($lead->id))->handle();

    Http::assertSent(function ($request) use ($lead) {
        $data = $request->data();

        return $request->url() === 'https://insert.example.test/lead'
            && $request->isForm()
            && $data['Token'] === 'tok-teste'
            && $data['CustomerOriginID'] === '42'
            && $data['PropertyID'] === '777'                 // internal_id do CRM, não o nosso id
            && $data['EntityName'] === $lead->name
            && $data['EntityEmail'] === $lead->email
            && $data['EntityPhone'] === $lead->phone
            && $data['CreateProfile'] === 'true'
            && $data['EntityCulture'] === 'pt'
            && $data['AssignBrokerIDFromProperty'] === 'true'
            && $data['IncludeOptIn'] === 'true'
            && $data['IncludeMailing'] === 'false'
            && str_contains($data['Message'], 'MF-777');
    });

    $lead->refresh();
    expect($lead->crm_status)->toBe(LeadStatus::Sent)
        ->and($lead->crm_response)->toEqualCanonicalizing(['status' => true, 'id' => 'L-1'])
        ->and($lead->sent_at)->not->toBeNull()
        ->and($lead->attempts)->toBe(1);
});

it('não envia PropertyID quando a lead não tem imóvel', function () {
    $lead = Lead::factory()->create(['source' => LeadSource::Valuation, 'payload' => ['city' => 'Cascais', 'area' => 120]]);
    Http::fake(['insert.example.test/*' => Http::response(['status' => true], 200)]);

    (new SendLeadToCasafari($lead->id))->handle();

    Http::assertSent(fn ($request) => ! array_key_exists('PropertyID', $request->data())
        && str_contains($request->data()['Message'], 'Quanto vale a minha casa')
        && str_contains($request->data()['Message'], 'City: Cascais'));
});

it('trata HTTP 200 com status=false como falha e lança exceção', function () {
    $lead = Lead::factory()->create();
    Http::fake(['insert.example.test/*' => Http::response(['status' => false, 'message' => 'Token inválido'], 200)]);

    expect(fn () => (new SendLeadToCasafari($lead->id))->handle())
        ->toThrow(RuntimeException::class, 'status=false');

    $lead->refresh();
    expect($lead->crm_status)->toBe(LeadStatus::Pending)     // ainda em retry
        ->and($lead->crm_response['message'])->toBe('Token inválido')
        ->and($lead->last_error)->toContain('Token inválido')
        ->and($lead->attempts)->toBe(1);
});

it('failed() marca failed, guarda o erro e notifica', function () {
    Notification::fake();
    $lead = Lead::factory()->create(['attempts' => 5]);

    (new SendLeadToCasafari($lead->id))->failed(new RuntimeException('CRM em baixo'));

    $lead->refresh();
    expect($lead->crm_status)->toBe(LeadStatus::Failed)
        ->and($lead->last_error)->toBe('CRM em baixo');

    Notification::assertSentOnDemand(LeadDeliveryFailed::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'alertas@example.test');
});

it('o job é idempotente: não reenvia leads já enviadas', function () {
    $lead = Lead::factory()->create(['crm_status' => LeadStatus::Sent]);
    Http::fake();

    (new SendLeadToCasafari($lead->id))->handle();

    Http::assertNothingSent();
});

it('sem token o job falha sem chamar o CRM', function () {
    config(['casafari.token' => null]);
    $lead = Lead::factory()->create();
    Http::fake();

    expect(fn () => (new SendLeadToCasafari($lead->id))->handle())->toThrow(RuntimeException::class, 'CASAFARI_TOKEN');
    Http::assertNothingSent();
});

it('tem retries com backoff crescente', function () {
    $job = new SendLeadToCasafari(1);

    expect($job->tries)->toBe(5)
        ->and($job->backoff)->toBe([60, 300, 900, 3600]);
});

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
