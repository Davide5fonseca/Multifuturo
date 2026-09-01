<?php

/*
 * Portal da equipa: entrada única (/entrar), verificação em duas etapas,
 * página de escolha (/portal), acesso aos módulos e gestão na Equipa.
 */

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\MfaCode;
use App\Models\User;
use App\Notifications\MfaCodeNotification;
use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function utilizadorAtivo(array $overrides = []): User
{
    return User::factory()->create(array_merge(['name' => 'Ana Silva', 'email' => 'ana@multifuturo.test', 'password' => Hash::make('segredo-123'), 'is_admin' => false, 'is_active' => true], $overrides));
}

beforeEach(function () {
    config(['portal.mfa' => false]);
    RateLimiter::clear('login:ana@multifuturo.test|127.0.0.1');
});

it('sem sessão, o backoffice e o portal mandam para /entrar; com sessão, /entrar manda para o portal', function () {
    $this->get('/portal')->assertRedirect('/entrar');
    $this->get('/admin')->assertRedirect('/entrar');
    $this->get('/entrar')->assertOk()->assertSee('Entrar')->assertSee('Manter sessão iniciada');

    $this->actingAs(utilizadorAtivo())->get('/entrar')->assertRedirect('/portal');
});

it('entra com email e palavra-passe certos e aterra no portal; errados ficam de fora', function () {
    $user = utilizadorAtivo();

    $this->post('/entrar', ['email' => 'ana@multifuturo.test', 'password' => 'errada'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/entrar', ['email' => 'ANA@multifuturo.test', 'password' => 'segredo-123', 'remember' => '1'])
        ->assertRedirect('/portal')
        ->assertCookie(Auth::guard('web')->getRecallerName(), null, false);
    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('uma conta desativada não entra e, se já tiver sessão, é posta fora no pedido seguinte', function () {
    $user = utilizadorAtivo(['is_active' => false]);

    $this->post('/entrar', ['email' => 'ana@multifuturo.test', 'password' => 'segredo-123'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $ativo = utilizadorAtivo(['email' => 'rui@multifuturo.test']);
    $this->actingAs($ativo)->get('/portal')->assertOk();
    $ativo->forceFill(['is_active' => false])->save();
    $this->actingAs($ativo)->get('/portal')->assertRedirect('/entrar');
    $this->assertGuest();
});

it('cinco tentativas falhadas bloqueiam o email durante um minuto', function () {
    utilizadorAtivo();

    foreach (range(1, 5) as $i) {
        $this->post('/entrar', ['email' => 'ana@multifuturo.test', 'password' => 'errada']);
    }

    $this->post('/entrar', ['email' => 'ana@multifuturo.test', 'password' => 'segredo-123'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Demasiadas tentativas');
    $this->assertGuest();
});

it('com a verificação em duas etapas, a sessão só começa depois do código enviado por email', function () {
    config(['portal.mfa' => true]);
    Notification::fake();
    $user = utilizadorAtivo();

    $this->post('/entrar', ['email' => 'ana@multifuturo.test', 'password' => 'segredo-123', 'remember' => '1'])
        ->assertRedirect('/verificar');
    $this->assertGuest();

    Notification::assertSentTo($user, MfaCodeNotification::class);
    $record = MfaCode::where('user_id', $user->id)->first();
    expect($record)->not->toBeNull()->and($record->code_hash)->not->toMatch('/^\d{6}$/');

    $this->get('/verificar')->assertOk()->assertSee('an•@multifuturo.test');

    // Código errado: fica de fora e conta uma tentativa.
    $this->post('/verificar', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    // Código certo: para o sabermos no teste, trocamos o hash por um conhecido.
    $record->update(['code_hash' => Hash::make('123456')]);
    $this->post('/verificar', ['code' => '123456'])->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);
    expect($record->fresh()->used_at)->not->toBeNull();
});

it('o código expira, queima-se ao fim de cinco tentativas e o reenvio tem intervalo', function () {
    config(['portal.mfa' => true]);
    Notification::fake();
    $user = utilizadorAtivo();
    $mfa = app(MfaService::class);

    $mfa->send($user);
    $record = MfaCode::where('user_id', $user->id)->first();
    $record->update(['code_hash' => Hash::make('123456')]);

    expect($mfa->secondsUntilResend($user))->toBeGreaterThan(0);

    foreach (range(1, 5) as $i) {
        expect($mfa->verify($user, '999999'))->toBe(MfaService::WRONG);
    }
    expect($mfa->verify($user, '123456'))->toBe(MfaService::TOO_MANY)
        ->and($mfa->verify($user, '123456'))->toBe(MfaService::EXPIRED);

    $mfa->send($user);
    MfaCode::where('user_id', $user->id)->whereNull('used_at')->update(['expires_at' => now()->subMinute()]);
    expect($mfa->verify($user, '123456'))->toBe(MfaService::EXPIRED);

    $this->travel(61)->seconds();
    expect($mfa->secondsUntilResend($user))->toBeNull();
});

it('o portal mostra a cada pessoa os seus módulos; administradores veem todos; o Site é para toda a gente', function () {
    $admin = utilizadorAtivo(['email' => 'chefe@multifuturo.test', 'is_admin' => true]);
    $comAcesso = utilizadorAtivo(['email' => 'rui@multifuturo.test']);
    $comAcesso->syncModules(['backoffice']);
    $semAcesso = utilizadorAtivo(['email' => 'novo@multifuturo.test']);
    $semAcesso->syncModules([]);

    $this->actingAs($admin)->get('/portal')->assertOk()->assertSee('Olá, Ana')->assertSee('Site')->assertSee('Backoffice')->assertSee('/admin')->assertSee('Equipa');
    $this->flushSession();
    $this->actingAs($comAcesso)->get('/portal')->assertOk()->assertSee('Backoffice')->assertDontSee('Sem módulos atribuídos');
    $this->flushSession();
    // O Site é público: quem não tem acessos vê-o na mesma, mas não vê o Backoffice.
    $this->actingAs($semAcesso)->get('/portal')->assertOk()->assertSee('Site')->assertDontSee('Backoffice')->assertSee('target="_blank"', false);
});

it('o backoffice só abre a quem tem o módulo backoffice (ou é administrador)', function () {
    $admin = utilizadorAtivo(['email' => 'chefe@multifuturo.test', 'is_admin' => true]);
    $comAcesso = utilizadorAtivo(['email' => 'rui@multifuturo.test']);
    $comAcesso->syncModules(['backoffice']);
    $semAcesso = utilizadorAtivo(['email' => 'novo@multifuturo.test']);
    $semAcesso->syncModules([]);

    // Entre pessoas limpa-se a sessão: o AuthenticateSession do Filament expulsa
    // uma sessão cujo hash de palavra-passe não bate com o utilizador atual.
    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->flushSession();
    $this->actingAs($comAcesso)->get('/admin')->assertOk();
    $this->flushSession();
    $this->actingAs($semAcesso)->get('/admin')->assertForbidden();
});

it('terminar sessão volta a /entrar e a sessão acaba mesmo', function () {
    $user = utilizadorAtivo();

    $this->actingAs($user)->post('/sair')->assertRedirect('/entrar');
    $this->assertGuest();
    $this->get('/portal')->assertRedirect('/entrar');
});

it('a Equipa atribui módulos e desativa contas', function () {
    $admin = utilizadorAtivo(['email' => 'chefe@multifuturo.test', 'is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Rui Novo', 'email' => 'rui@multifuturo.test', 'password' => 'palavra-passe-1', 'is_admin' => false, 'is_active' => true, 'modules' => ['backoffice']])
        ->call('create')
        ->assertHasNoFormErrors();

    $rui = User::where('email', 'rui@multifuturo.test')->first();
    expect($rui->canAccessModule('backoffice'))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $rui->getKey()])
        ->assertFormSet(['modules' => ['backoffice']])
        ->fillForm(['modules' => [], 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $rui->refresh();
    expect($rui->canAccessModule('backoffice'))->toBeFalse()
        ->and($rui->is_active)->toBeFalse();
});

it('a recuperação de palavra-passe do Filament continua a existir e é para lá que o login aponta', function () {
    $this->get('/entrar')->assertOk()->assertSee('/admin/password-reset/request');
    $this->get('/admin/password-reset/request')->assertOk();
});

it('o login e o portal são uma plataforma própria: não mencionam a agência nem ligam ao site', function () {
    config(['portal.name' => 'Plataforma X']);

    $this->get('/entrar')->assertOk()
        ->assertSee('Plataforma X')
        ->assertDontSee('Multifuturo')
        ->assertDontSee('imóve')
        ->assertDontSee('images/marca');

    // O cartão "Site" liga ao website — é um módulo; o que não pode haver é a marca da agência no portal em si.
    $this->actingAs(utilizadorAtivo())->get('/portal')->assertOk()
        ->assertSee('Plataforma X')
        ->assertDontSee('Multifuturo')
        ->assertDontSee('images/marca');
});
