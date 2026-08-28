<?php

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| A carteira é gerida no backoffice (/admin) e escrita diretamente na base de
| dados: não há sincronização com nenhum CRM. Três tarefas agendadas: a cópia
| de segurança diária, a importação semanal dos valores por m² do INE e o
| envio dos alertas de imóveis de hora a hora — e a limpeza diária dos
| registos de consentimento com mais de 24 meses (model:prune).
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

/*
| Valores de referência do INE para o simulador "Quanto vale a minha casa?".
| Uma vez por semana chega: o INE publica a avaliação bancária ao mês e as
| vendas ao trimestre. Também se corre à mão no backoffice (Importar do INE).
*/
Schedule::command('valuation:import-ine')
    ->weeklyOn(1, '04:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => Log::error('A importação dos valores do INE falhou.'));

/*
| Alertas de imóveis: de hora a hora, os imóveis publicados desde o último
| envio para quem pediu "avise-me". Leve — uma pesquisa por alerta ativo.
*/
Schedule::command('alerts:send')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => Log::error('O envio dos alertas de imóveis falhou.'));

/*
| Registos de consentimento de cookies: a prova guarda-se 24 meses
| (ConsentLog::prunable) e depois apaga-se — dados a mais são risco a mais.
*/
Schedule::command('model:prune')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('A limpeza dos registos de consentimento falhou.'));
