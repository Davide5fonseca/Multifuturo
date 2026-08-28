<?php

/*
 * Alertas de imóveis: formulário na listagem, double opt-in, ligações
 * assinadas, published_at, envio de hora a hora e backoffice.
 */

use App\Filament\Resources\PropertyAlerts\Pages\ListPropertyAlerts;
use App\Http\Requests\StoreAlertRequest;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyAlert;
use App\Models\User;
use App\Notifications\ConfirmPropertyAlert;
use App\Notifications\PropertyAlertDigest;
use App\Support\PropertyCache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/** Pedido válido de alerta, com timestamp assinado "antigo" o suficiente. */
function alertPayload(array $overrides = []): array
{
    return array_merge([
        'listing' => 'buy',
        'email' => 'Ana@Example.test',
        'consent' => '1',
        'criteria' => ['city' => 'Sintra', 'bedrooms' => '3', 'price_max' => '300000'],
        'form_ts' => StoreAlertRequest::signedTimestamp(time() - 30),
    ], $overrides);
}

/** Alerta confirmado ontem para T3+ em Sintra até 300 000 €. */
function alertaSintra(array $overrides = []): PropertyAlert
{
    return PropertyAlert::query()->create(array_merge([
        'email' => 'ana@example.test',
        'locale' => 'pt',
        'listing' => 'buy',
        'criteria' => ['bedrooms' => 3, 'city' => 'Sintra', 'price_max' => 300000],
        'token' => PropertyAlert::newToken(),
        'confirmed_at' => now()->subDay(),
    ], $overrides));
}

beforeEach(function () {
    Notification::fake();
    PropertyCache::flush();
    RateLimiter::clear('leads:m:'.Lead::hashIp('127.0.0.1'));
    RateLimiter::clear('leads:h:'.Lead::hashIp('127.0.0.1'));
});

it('a listagem mostra o formulário "avise-me" com os filtros ativos', function () {
    $this->get(route('buy', ['concelho' => 'Sintra', 'tipologia' => 3, 'preco_max' => 300000]))->assertOk()
        ->assertSee('Avise-me de novos imóveis')
        ->assertSee('Venda · Sintra · T3+')
        ->assertSee('<input type="hidden" name="criteria[city]" value="Sintra">', false)
        ->assertSee('<input type="hidden" name="criteria[bedrooms]" value="3">', false)
        ->assertSee('<input type="hidden" name="criteria[price_max]" value="300000">', false)
        ->assertSee('<input type="hidden" name="listing" value="buy">', false);

    $this->get(route('rent'))->assertOk()
        ->assertSee('<input type="hidden" name="listing" value="rent">', false)
        ->assertDontSee('name="criteria[', false);
});

it('cria o alerta por confirmar e envia o email de confirmação; spam e falta de consentimento não criam nada', function () {
    $this->post(route('alerts.store'), alertPayload())->assertRedirect()->assertSessionHas('alert_sent');

    $alert = PropertyAlert::first();

    expect($alert->email)->toBe('ana@example.test')
        ->and($alert->confirmed_at)->toBeNull()
        ->and($alert->criteria)->toEqual(['bedrooms' => 3, 'city' => 'Sintra', 'price_max' => 300000]) // a ordem das chaves é a do jsonb
        ->and($alert->listing)->toBe('buy')
        ->and($alert->locale)->toBe('pt')
        ->and($alert->policy_version)->toBe(config('agency.privacy_policy_version'))
        ->and($alert->ip_hash)->toHaveLength(64)
        ->and($alert->token)->toHaveLength(48);

    Notification::assertSentOnDemand(ConfirmPropertyAlert::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.test');

    // Honeypot preenchido: aceite em silêncio, nada gravado.
    $this->post(route('alerts.store'), alertPayload(['email' => 'bot@example.test', 'website' => 'http://spam']))->assertRedirect();
    // Sem consentimento: erro no saco "alerts".
    $this->post(route('alerts.store'), alertPayload(['email' => 'c@example.test', 'consent' => '']))->assertSessionHasErrorsIn('alerts', ['consent']);

    expect(PropertyAlert::count())->toBe(1);
});

it('o mesmo email com os mesmos critérios não duplica; se já está confirmado, avisa que existe', function () {
    $this->post(route('alerts.store'), alertPayload());
    $this->post(route('alerts.store'), alertPayload(['email' => 'ANA@example.test']));

    expect(PropertyAlert::count())->toBe(1);
    Notification::assertSentOnDemandTimes(ConfirmPropertyAlert::class, 2); // reenvia enquanto não confirma

    PropertyAlert::first()->forceFill(['confirmed_at' => now()])->save();

    $this->post(route('alerts.store'), alertPayload())->assertSessionHas('alert_exists');

    Notification::assertSentOnDemandTimes(ConfirmPropertyAlert::class, 2);
    expect(PropertyAlert::count())->toBe(1);
});

it('as ligações assinadas confirmam e cancelam; adulteradas dão 403; reconfirmar reativa', function () {
    $alert = alertaSintra(['confirmed_at' => null]);

    $this->get(URL::signedRoute('alerts.confirm', ['locale' => 'pt', 'token' => $alert->token]))->assertOk()->assertSee('Alerta confirmado');
    expect($alert->fresh()->confirmed_at)->not->toBeNull();

    $this->get(route('alerts.confirm', ['locale' => 'pt', 'token' => $alert->token]))->assertForbidden();

    $this->get(URL::signedRoute('alerts.unsubscribe', ['locale' => 'pt', 'token' => $alert->token]))->assertOk()->assertSee('Alerta cancelado');
    expect($alert->fresh()->unsubscribed_at)->not->toBeNull();

    $this->get(URL::signedRoute('alerts.confirm', ['locale' => 'pt', 'token' => $alert->token]))->assertOk();
    expect($alert->fresh()->unsubscribed_at)->toBeNull();

    $this->get(URL::signedRoute('alerts.confirm', ['locale' => 'pt', 'token' => 'inexistente']))->assertOk()->assertSee('Ligação inválida');
});

it('published_at fica escrito na primeira publicação e nunca mais muda', function () {
    $p = Property::factory()->create(['is_active' => false]);
    expect($p->fresh()->published_at)->toBeNull();

    $p->update(['is_active' => true]);
    $first = $p->fresh()->published_at;
    expect($first)->not->toBeNull();

    $this->travel(2)->hours();
    $p->update(['price' => 123456]);
    $p->update(['is_active' => false]);
    $p->update(['is_active' => true]);

    expect($p->fresh()->published_at->equalTo($first))->toBeTrue();
});

it('alerts:send envia só o que encaixa e é novo desde a confirmação, e não repete', function () {
    $alert = alertaSintra();
    alertaSintra(['email' => 'pendente@example.test', 'confirmed_at' => null]);
    alertaSintra(['email' => 'cancelado@example.test', 'unsubscribed_at' => now()]);

    $match = Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 250000]);
    $match2 = Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 4, 'price' => 299000]);
    Property::factory()->create(['city' => 'Lisboa', 'bedrooms' => 3, 'price' => 250000]);                 // outro concelho
    Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 2, 'price' => 250000]);                 // tipologia abaixo
    Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 350000]);                 // acima do preço
    Property::factory()->forRent()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 900]);         // arrendamento
    Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 250000, 'is_active' => false]); // não publicado
    Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 250000])
        ->forceFill(['published_at' => now()->subDays(2)])->saveQuietly();                                  // anterior à confirmação

    $this->artisan('alerts:send')->assertSuccessful();

    Notification::assertSentOnDemandTimes(PropertyAlertDigest::class, 1);
    Notification::assertSentOnDemand(PropertyAlertDigest::class, function ($notification, $channels, $notifiable) use ($match, $match2) {
        $mail = $notification->toMail($notifiable);
        // As linhas depois do botão (a de cancelar) ficam em outroLines.
        $text = implode("\n", array_map('strval', array_merge($mail->introLines, $mail->outroLines)));

        return $notifiable->routes['mail'] === 'ana@example.test'
            && str_contains($mail->subject, '2 imóveis novos')
            && str_contains($text, $match->title) && str_contains($text, $match2->title)
            && substr_count($text, '/imoveis/') === 2
            && str_contains($text, 'Cancelar o alerta')
            && str_contains($mail->actionUrl, 'concelho=Sintra');
    });

    $fresh = $alert->fresh();
    expect($fresh->sent_count)->toBe(1)->and($fresh->last_sent_at)->not->toBeNull();

    // Nada de novo: nada sai.
    $this->artisan('alerts:send')->assertSuccessful();
    Notification::assertSentOnDemandTimes(PropertyAlertDigest::class, 1);

    // Entra mais um: só esse.
    $this->travel(1)->minutes();
    Property::factory()->create(['city' => 'Sintra', 'bedrooms' => 3, 'price' => 200000]);
    $this->artisan('alerts:send')->assertSuccessful();

    Notification::assertSentOnDemandTimes(PropertyAlertDigest::class, 2);
    expect($alert->fresh()->sent_count)->toBe(2);
});

it('o backoffice lista os alertas com critérios e estado', function () {
    $this->actingAs(User::factory()->create());
    alertaSintra();
    alertaSintra(['email' => 'pendente@example.test', 'confirmed_at' => null]);

    Livewire::test(ListPropertyAlerts::class)->assertOk()
        ->assertSee('ana@example.test')
        ->assertSee('Sintra')
        ->assertSee('Ativo')
        ->assertSee('Por confirmar');
});
