<?php

namespace App\Providers;

use App\Support\AgencyCompliance;
use App\Support\AppUrl;
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
        AppUrl::forceFromConfig();

        // Em produção, arrancar sem AMI é proibido (ver AgencyCompliance).
        AgencyCompliance::assertAmi($this->app->environment());

        // Preload dos assets do Vite com prioridade agressiva (fontes já vão no layout).
        Vite::usePrefetchStrategy('aggressive');
    }
}
