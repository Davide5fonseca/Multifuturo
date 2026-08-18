<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| Requer o cron do sistema a correr `php artisan schedule:run` a cada minuto
| (em local: `.\sail.ps1 artisan schedule:work`).
|
| casafari:sync — de hora a hora, ao minuto 7 (fora da hora certa, quando os
| feeds costumam estar a ser regenerados). withoutOverlapping garante que uma
| execução lenta não se sobrepõe à seguinte; o output vai por email em caso de
| falha (exit code != 0: feed vazio, erro HTTP, erros de mapeamento).
|
*/

$sync = Schedule::command('casafari:sync')
    ->hourlyAt(7)
    ->withoutOverlapping(expiresAt: 120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/casafari-sync.log'));

if ($email = config('casafari.alert_email')) {
    $sync->emailOutputOnFailure($email);
}
