<?php

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| A carteira é gerida no backoffice (/admin) e escrita diretamente na base de
| dados: não há sincronização com nenhum CRM. A única tarefa agendada é a
| cópia de segurança diária.
|
*/

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
| Cópia de segurança diária, sempre à mesma hora (config/backup.php).
|
| withoutOverlapping: se uma cópia demorar mais do que o esperado, a seguinte
| espera em vez de correr por cima. runInBackground: não prende o agendador.
*/
Schedule::command('backup:run')
    ->dailyAt((string) config('backup.at'))
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => Log::error('A cópia de segurança diária falhou.'));
