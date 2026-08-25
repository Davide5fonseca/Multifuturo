<?php

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| Sem ligação ao CRM (decisão de 2026-08-19), não há sincronização agendada:
| a carteira é gerida no backoffice (/admin) e escrita diretamente na base de
| dados. O comando casafari:sync mantém-se disponível apenas para importações
| pontuais de ficheiro (casafari:sync --file=…), executadas à mão.
|
| Se voltar a existir um feed automático, reativar aqui o bloco de agendamento
| (hourlyAt + withoutOverlapping + emailOutputOnFailure) — ver o histórico do
| ficheiro no git (Fase 3).
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
