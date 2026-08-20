<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\Property;
use App\Observers\PropertyObserver;
use App\Support\AgencyCompliance;
use App\Support\AppUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // URLs absolutos sempre a partir de config('app.url'), nunca do Host do pedido.
        // Exceção: em desenvolvimento local seguem o host usado (localhost ou multifuturo.test),
        // senão os assets/links partem quando não há entrada no ficheiro hosts.
        // Mas se o APP_URL indicar uma subpasta (http://localhost/multifuturo), é preciso
        // forçar sempre — senão os links saíam sem o prefixo e davam 404.
        if (! $this->app->environment('local') || AppUrl::pathPrefix() !== '') {
            AppUrl::forceFromConfig();
        }

        // Em produção, arrancar sem AMI é proibido (ver AgencyCompliance).
        AgencyCompliance::assertAmi($this->app->environment());

        // Histórico de alterações dos imóveis (dashboard do backoffice).
        Property::observe(PropertyObserver::class);

        // Preload dos assets do Vite com prioridade agressiva (fontes já vão no layout).
        Vite::usePrefetchStrategy('aggressive');

        // Anti-spam dos formulários de lead: por IP, 5 por minuto e 20 por hora.
        // A chave usa o hash do IP — o IP em claro não entra na cache.
        RateLimiter::for('leads', function (Request $request): array {
            $key = Lead::hashIp($request->ip()) ?? 'anon';

            return [
                Limit::perMinute(5)->by('leads:m:'.$key),
                Limit::perHour(20)->by('leads:h:'.$key),
            ];
        });
    }
}
