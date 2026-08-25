<?php

/*
 * Responder a uma dúvida chegada pelo site: o email sai, a resposta fica
 * registada, e a lista passa a mostrar a dúvida como respondida.
 */

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeadReply;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Ana Silva']));
    Notification::fake();
});

it('a secção chama-se Dúvidas dos clientes', function () {
    expect(LeadResource::getPluralModelLabel())->toBe('Dúvidas dos clientes')
        ->and(LeadResource::getModelLabel())->toBe('dúvida');
});

it('responder envia o email ao cliente e regista a resposta', function () {
    $imovel = Property::factory()->create(['reference' => 'MF-2051']);
    $duvida = Lead::factory()->create([
        'email' => 'cliente@example.test',
        'name' => 'João Cliente',
        'property_id' => $imovel->id,
    ]);

    expect($duvida->foiRespondida())->toBeFalse();

    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->callAction('responder', ['body' => 'O imóvel continua disponível. Podemos marcar uma visita esta semana.']);

    Notification::assertSentOnDemand(LeadReply::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'cliente@example.test';
    });

    $duvida->refresh();

    expect($duvida->foiRespondida())->toBeTrue()
        ->and($duvida->replies)->toHaveCount(1)
        ->and($duvida->replies[0]['author'])->toBe('Ana Silva')
        ->and($duvida->replies[0]['body'])->toContain('marcar uma visita');
});

it('o email leva a referência do imóvel e responde para a agência', function () {
    config(['agency.email' => 'geral@multifuturo.test', 'agency.name' => 'Multifuturo Propriedades']);
    $imovel = Property::factory()->create(['reference' => 'MF-2051']);
    $duvida = Lead::factory()->create(['email' => 'cliente@example.test', 'property_id' => $imovel->id]);

    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->callAction('responder', ['body' => 'Resposta de teste com corpo suficiente.']);

    Notification::assertSentOnDemand(LeadReply::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return str_contains($mail->subject, 'MF-2051')
            && str_contains($mail->subject, 'Multifuturo Propriedades')
            && $mail->replyTo[0][0] === 'geral@multifuturo.test';
    });
});

it('várias respostas ficam todas registadas, por ordem', function () {
    $duvida = Lead::factory()->create(['email' => 'cliente@example.test']);

    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->callAction('responder', ['body' => 'Primeira resposta ao cliente.']);
    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->callAction('responder', ['body' => 'Segunda resposta, dias depois.']);

    $replies = $duvida->refresh()->replies;

    expect($replies)->toHaveCount(2)
        ->and($replies[0]['body'])->toContain('Primeira')
        ->and($replies[1]['body'])->toContain('Segunda');
});

it('o botão de telefonar só aparece quando há telefone', function () {
    $comTelefone = Lead::factory()->create(['phone' => '+351 912 345 678']);
    $semTelefone = Lead::factory()->create(['phone' => null]);

    Livewire::test(EditLead::class, ['record' => $comTelefone->getKey()])
        ->assertActionVisible('telefonar');

    Livewire::test(EditLead::class, ['record' => $semTelefone->getKey()])
        ->assertActionHidden('telefonar');
});

it('a resposta enviada nunca é editada pela ficha', function () {
    // A dúvida é o registo do que a pessoa enviou: gravar a ficha não pode
    // apagar nem alterar o histórico de respostas.
    $duvida = Lead::factory()->create(['email' => 'cliente@example.test']);

    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->callAction('responder', ['body' => 'Resposta que tem de sobreviver.']);

    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->call('save');

    expect($duvida->refresh()->replies)->toHaveCount(1)
        ->and($duvida->replies[0]['body'])->toContain('sobreviver');
});

it('a dúvida mostra e abre o imóvel a que diz respeito', function () {
    $imovel = Property::factory()->create(['reference' => 'MF-2051']);
    $duvida = Lead::factory()->create(['property_id' => $imovel->id]);
    $semImovel = Lead::factory()->create(['property_id' => null]);

    // O campo mostra a referência (vem de uma relação, não de uma coluna).
    Livewire::test(EditLead::class, ['record' => $duvida->getKey()])
        ->assertFormSet(['imovel' => 'MF-2051'])
        ->assertActionVisible('verImovel');

    Livewire::test(EditLead::class, ['record' => $semImovel->getKey()])
        ->assertActionHidden('verImovel');
});
