<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Equipa e acessos — só no portal, só para administradores.
 *
 * Cria e edita as contas, define quem é administrador, quem está ativo e
 * que módulos cada pessoa vê. Ninguém se despromove, se desativa nem se
 * apaga a si próprio: senão a agência ficava sem forma de gerir contas.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        return view('portal.team.index', [
            'users' => User::query()->with('moduleAccess')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('portal.team.form', ['user' => new User(['is_active' => true]), 'modules' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $user->syncModules($data['modules'] ?? []);

        return redirect()->route('team.index')->with('status', "Conta de {$user->name} criada. Dê-lhe a palavra-passe por um canal seguro — pode mudá-la no perfil.");
    }

    public function edit(User $user): View
    {
        return view('portal.team.form', ['user' => $user, 'modules' => $user->moduleAccess()->pluck('module')->all()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $self = $user->is($request->user());

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        // A própria conta nunca perde administração nem é desativada por aqui.
        if (! $self) {
            $user->is_admin = (bool) ($data['is_admin'] ?? false);
            $user->is_active = (bool) ($data['is_active'] ?? false);
        }

        $user->save();
        $user->syncModules($data['modules'] ?? []);

        return redirect()->route('team.index')->with('status', "Conta de {$user->name} atualizada.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('team.edit', $user)->withErrors(['conta' => 'Não pode apagar a sua própria conta.']);
        }

        $nome = $user->name;
        $user->delete();

        return redirect()->route('team.index')->with('status', "Conta de {$nome} apagada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(Modules::options()))],
        ], [], [
            'name' => 'nome',
            'email' => 'email',
            'password' => 'palavra-passe',
            'modules' => 'módulos',
        ]);
    }
}
