<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Segunda etapa do login: o código de seis algarismos enviado por email.
 * É só aqui que a sessão fica realmente iniciada.
 */
class MfaController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('mfa.user_id')) {
            return redirect()->route('login');
        }

        return view('portal.verify', ['maskedEmail' => self::mask((string) $request->session()->get('mfa.email', ''))]);
    }

    public function verify(Request $request, MfaService $mfa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'O código tem seis algarismos.'], ['code' => 'código']);

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $result = $mfa->verify($user, (string) $request->input('code'));

        if ($result !== MfaService::OK) {
            throw ValidationException::withMessages(['code' => match ($result) {
                MfaService::EXPIRED => 'O código expirou. Peça um novo.',
                MfaService::TOO_MANY => 'Demasiadas tentativas. Peça um novo código.',
                default => 'Código incorreto.',
            }]);
        }

        $remember = (bool) $request->session()->get('mfa.remember', false);
        $request->session()->forget(['mfa.user_id', 'mfa.remember', 'mfa.email']);

        LoginController::startSession($request, $user, $remember);

        return redirect()->route('portal');
    }

    public function resend(Request $request, MfaService $mfa): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($wait = $mfa->secondsUntilResend($user)) {
            return back()->withErrors(['code' => "Aguarde {$wait} segundos antes de pedir um novo código."]);
        }

        $mfa->send($user);

        return back()->with('status', 'Enviámos um novo código para o seu email.');
    }

    /** A conta tem de continuar ativa entre o envio do código e a sua introdução. */
    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('mfa.user_id');

        return $id ? User::query()->where('is_active', true)->find($id) : null;
    }

    /** su•••@nxs.pt — mostra que email recebeu o código sem o expor todo. */
    public static function mask(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
