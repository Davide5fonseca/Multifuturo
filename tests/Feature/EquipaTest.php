<?php

/*
 * Equipa e acessos — só no portal (/gestao/equipa), só para administradores.
 * Ninguém se despromove, desativa nem apaga a si próprio.
 */

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;

it('a equipa só existe no portal e só para administradores; o backoffice já não a tem', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/gestao/equipa')->assertForbidden();
    $this->flushSession();

    $this->actingAs(User::factory()->create(['is_admin' => true, 'name' => 'Chefe Silva']))->get('/gestao/equipa')
        ->assertOk()->assertSee('Equipa e acessos')->assertSee('Chefe Silva')->assertSee('Nova conta');

    $this->get('/admin/users')->assertNotFound();
    expect(class_exists(UserResource::class))->toBeFalse();
});

it('um administrador cria uma conta com módulos; a palavra-passe fica cifrada', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get('/gestao/equipa/nova')->assertOk()->assertSee('Criar conta');

    $this->post('/gestao/equipa', [
        'name' => 'Ana Silva', 'email' => 'ana@multifuturo.test', 'password' => 'palavra-passe-forte',
        'is_active' => '1', 'modules' => ['backoffice'],
    ])->assertRedirect('/gestao/equipa')->assertSessionHas('status');

    $ana = User::where('email', 'ana@multifuturo.test')->firstOrFail();

    expect($ana->isAdmin())->toBeFalse()
        ->and($ana->is_active)->toBeTrue()
        ->and($ana->password)->not->toBe('palavra-passe-forte')
        ->and(Hash::check('palavra-passe-forte', $ana->password))->toBeTrue()
        ->and($ana->canAccessModule('backoffice'))->toBeTrue()
        ->and($ana->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('valida: email repetido, palavra-passe curta, módulo desconhecido', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true, 'email' => 'chefe@multifuturo.test']));

    $this->from('/gestao/equipa/nova')->post('/gestao/equipa', ['name' => 'X', 'email' => 'chefe@multifuturo.test', 'password' => 'curta', 'modules' => ['fantasma']])
        ->assertRedirect('/gestao/equipa/nova')
        ->assertSessionHasErrors(['email', 'password', 'modules.0']);

    expect(User::count())->toBe(1);
});

it('editar sem palavra-passe mantém a que estava; com palavra-passe substitui-a; os módulos sincronizam', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $pessoa = User::factory()->create(['password' => Hash::make('a-antiga')]);

    $this->get("/gestao/equipa/{$pessoa->id}")->assertOk()->assertSee('deixe em branco para manter')->assertDontSee('a-antiga');

    $this->put("/gestao/equipa/{$pessoa->id}", ['name' => 'Nome Novo', 'email' => $pessoa->email, 'password' => '', 'is_active' => '1', 'modules' => []])
        ->assertRedirect('/gestao/equipa');
    $pessoa->refresh();
    expect($pessoa->name)->toBe('Nome Novo')
        ->and(Hash::check('a-antiga', $pessoa->password))->toBeTrue()
        ->and($pessoa->canAccessModule('backoffice'))->toBeFalse();

    $this->put("/gestao/equipa/{$pessoa->id}", ['name' => 'Nome Novo', 'email' => $pessoa->email, 'password' => 'a-nova-palavra-passe', 'is_active' => '', 'modules' => ['backoffice']])
        ->assertRedirect('/gestao/equipa');
    $pessoa->refresh();
    expect(Hash::check('a-nova-palavra-passe', $pessoa->password))->toBeTrue()
        ->and($pessoa->is_active)->toBeFalse()
        ->and($pessoa->canAccessModule('backoffice'))->toBeTrue();
});

it('ninguém se apaga, se despromove nem se desativa a si próprio', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Chefe']);
    $this->actingAs($admin);

    $this->get("/gestao/equipa/{$admin->id}")->assertOk()->assertDontSee('Apagar conta')->assertSee('Não pode retirar o seu próprio acesso');

    // Mesmo forçando o pedido, a própria conta não perde administração nem fica desativada.
    $this->put("/gestao/equipa/{$admin->id}", ['name' => 'Chefe', 'email' => $admin->email, 'password' => '', 'is_admin' => '', 'is_active' => '', 'modules' => []])
        ->assertRedirect('/gestao/equipa');
    $admin->refresh();
    expect($admin->is_admin)->toBeTrue()->and($admin->is_active)->toBeTrue();

    $this->delete("/gestao/equipa/{$admin->id}")->assertRedirect("/gestao/equipa/{$admin->id}")->assertSessionHasErrors('conta');
    expect(User::find($admin->id))->not->toBeNull();

    // Noutra conta, apagar funciona.
    $outro = User::factory()->create(['is_admin' => true]);
    $this->get("/gestao/equipa/{$outro->id}")->assertOk()->assertSee('Apagar conta');
    $this->delete("/gestao/equipa/{$outro->id}")->assertRedirect('/gestao/equipa');
    expect(User::find($outro->id))->toBeNull();
});
