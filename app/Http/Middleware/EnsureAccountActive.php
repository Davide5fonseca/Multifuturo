<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uma conta desativada é posta fora no pedido seguinte — mesmo quem tinha
 * sessão aberta ou entrou pelo "manter sessão iniciada", que não passa pelo
 * formulário de login. Aplica-se ao portal e a todos os módulos.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'A sua conta foi desativada. Fale com um administrador.']);
        }

        return $next($request);
    }
}
