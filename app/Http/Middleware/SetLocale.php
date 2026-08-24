<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Põe a aplicação no idioma do endereço e faz com que todos os route() gerados
 * a partir daqui fiquem no mesmo idioma — sem que as views precisem de saber
 * que o site é multilingue.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! Locales::isEnabled($locale)) {
            $locale = Locales::default();
        }

        app()->setLocale($locale);

        // Todos os route() gerados a partir daqui ficam neste idioma, sem que as
        // views precisem de saber que o site é multilingue.
        URL::defaults(['locale' => $locale]);

        // O idioma sai dos parâmetros da rota: o Laravel passa-os aos
        // controladores por ordem, e sem isto o {locale} entrava como primeiro
        // argumento de cada método (PropertyController::show receberia "pt").
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
