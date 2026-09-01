<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Entrada única da equipa: email e palavra-passe.
 *
 * Com a verificação em duas etapas ligada (config portal.mfa), a sessão NÃO
 * começa aqui: começa depois do código (MfaController). Desligada, as
 * credenciais certas bastam.
 */
class LoginController extends Controller
{
    /**
     * Hash de um valor que não corresponde a ninguém: o Hash::check corre
     * sempre, com ou sem conta, para o tempo de resposta não denunciar se o
     * email existe. Não é segredo nenhum.
     */
    private const DUMMY_HASH = '$2y$12$qy4D3gGol7huqGe9mi3nzet4gRnRocAEf52mRdeo8RRIvnbL6gG3S';

    public function show(): View
    {
        return view('portal.login');
    }

    public function store(Request $request, MfaService $mfa): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $key = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('portal.login_attempts'))) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => "Demasiadas tentativas. Tente outra vez dentro de {$seconds} segundos."]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->where('is_active', true)->first();
        $passwordOk = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'O email ou a palavra-passe não estão certos.']);
        }

        RateLimiter::clear($key);

        $remember = $request->boolean('remember');

        if (! config('portal.mfa')) {
            self::startSession($request, $user, $remember);

            return redirect()->route('portal');
        }

        $mfa->send($user);

        $request->session()->put(['mfa.user_id' => $user->id, 'mfa.remember' => $remember, 'mfa.email' => $user->email]);

        return redirect()->route('mfa.show');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sessão terminada.');
    }

    /** Iniciar a sessão de uma só maneira, venha de onde vier (com ou sem código). */
    public static function startSession(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        // Depois de entrar, o destino é sempre o portal — nunca um favorito antigo de um módulo.
        $request->session()->forget('url.intended');

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
