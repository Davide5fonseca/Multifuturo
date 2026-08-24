<?php

/*
 * Sino do backoffice: cada pedido novo do site cria uma notificação em base
 * de dados para toda a equipa (além do email à agência).
 */

use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

function bellPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'contact',
        'name' => 'Maria Teste',
        'email' => 'maria@example.test',
        'phone' => '+351 912 345 678',
        'message' => 'Gostava de saber mais.',
        'form_ts' => StoreLeadRequest::signedTimestamp(time() - 30),
    ], $overrides);
}

beforeEach(function () {
    config(['agency.email' => null]); // sem email: só interessa o sino
    RateLimiter::clear('leads:m:'.Lead::hashIp('127.0.0.1'));
    RateLimiter::clear('leads:h:'.Lead::hashIp('127.0.0.1'));
});

it('cada pedido novo acende o sino do backoffice para toda a equipa', function () {
    $users = User::factory()->count(2)->create();
    $property = Property::factory()->create(['reference' => 'MF-888']);

    $this->post(route('leads.store'), bellPayload(['source' => 'property', 'property_slug' => $property->slug]));

    foreach ($users as $user) {
        $notification = $user->notifications()->first();

        expect($notification)->not->toBeNull()
            ->and($notification->data['title'])->toContain('MF-888')
            ->and($notification->data['body'])->toContain('Maria Teste');
    }
});

it('o spam não acende o sino', function () {
    $user = User::factory()->create();

    $this->post(route('leads.store'), bellPayload(['website' => 'https://spam.example']));

    expect($user->notifications()->count())->toBe(0);
});
