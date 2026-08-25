<?php

/*
|--------------------------------------------------------------------------
| Cópias de segurança
|--------------------------------------------------------------------------
|
| Uma cópia por dia, sempre à mesma hora, com a base de dados e os ficheiros
| carregados (fotografias e documentos). O que não estiver aqui não é
| recuperável: não há mais nenhuma rede.
|
| A hora está de madrugada de propósito — é quando ninguém está a trabalhar no
| backoffice e a base de dados está parada.
|
*/

return [

    // Hora da cópia diária, no fuso da aplicação (24h, HH:MM).
    'at' => env('BACKUP_AT', '03:30'),

    // Quantos dias de cópias guardar. As mais antigas são apagadas no fim de
    // cada execução — sem isto, o disco enche-se em silêncio.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Onde ficam. Fora de public/: nunca são servidas a ninguém.
    'path' => storage_path('backups'),

];
