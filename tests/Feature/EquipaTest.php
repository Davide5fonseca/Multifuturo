<?php

/*
 * Contas da equipa: só os administradores as gerem, e ninguém se pode
 * despromover nem apagar a si próprio — senão a agência ficava sem forma de
 * criar contas.
 */

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('só os administradores chegam às contas da equipa', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]));
    expect(UserResource::canAccess())->toBeFalse();
    $this->get(UserResource::getUrl('index'))->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    expect(UserResource::canAccess())->toBeTrue();
    $this->get(UserResource::getUrl('index'))->assertOk();
});

it('um administrador cria uma conta para a equipa', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Silva',
            'email' => 'ana@multifuturo.test',
            'password' => 'palavra-passe-forte',
            'is_admin' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ana = User::where('email', 'ana@multifuturo.test')->firstOrFail();

    expect($ana->isAdmin())->toBeFalse()
        // A palavra-passe é guardada cifrada, nunca em claro.
        ->and($ana->password)->not->toBe('palavra-passe-forte')
        ->and(Hash::check('palavra-passe-forte', $ana->password))->toBeTrue()
        ->and($ana->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
});

it('editar sem escrever palavra-passe mantém a que estava', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $pessoa = User::factory()->create(['password' => Hash::make('a-antiga')]);

    Livewire::test(EditUser::class, ['record' => $pessoa->getKey()])
        // O formulário nunca traz a palavra-passe preenchida.
        ->assertFormSet(['password' => null])
        ->fillForm(['name' => 'Nome Novo'])
        ->call('save')
        ->assertHasNoFormErrors();

    $pessoa->refresh();

    expect($pessoa->name)->toBe('Nome Novo')
        ->and(Hash::check('a-antiga', $pessoa->password))->toBeTrue();
});

it('a nova palavra-passe substitui a antiga quando é escrita', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $pessoa = User::factory()->create(['password' => Hash::make('a-antiga')]);

    Livewire::test(EditUser::class, ['record' => $pessoa->getKey()])
        ->fillForm(['password' => 'a-nova-palavra-passe'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('a-nova-palavra-passe', $pessoa->refresh()->password))->toBeTrue();
});

it('ninguém se apaga nem se despromove a si próprio', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // Sem botão de apagar na própria conta.
    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->assertActionHidden('delete');

    // E o interruptor de administrador fica bloqueado.
    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->assertFormFieldDisabled('is_admin');

    // Noutra conta, ambos funcionam.
    $outro = User::factory()->create(['is_admin' => true]);
    Livewire::test(EditUser::class, ['record' => $outro->getKey()])
        ->assertActionVisible('delete')
        ->assertFormFieldEnabled('is_admin');
});
