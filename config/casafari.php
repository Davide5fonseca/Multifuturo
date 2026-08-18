<?php

/*
|--------------------------------------------------------------------------
| Integração CASAFARI CRM
|--------------------------------------------------------------------------
|
| Leitura: feed XML (Feedcruncher), consumido apenas pelo comando de sync e
| replicado em PostgreSQL. O site NUNCA lê do CRM em runtime.
| Escrita: API de leads, chamada apenas a partir da queue.
|
| Todos os valores vêm do .env; nenhum segredo fica neste ficheiro.
|
*/

return [

    // URL do feed XML completo da carteira (snapshot, não delta).
    'feed_url' => env('CASAFARI_FEED_URL'),

    // Endpoint de criação de leads.
    'lead_url' => env('CASAFARI_LEAD_URL', 'https://insert.moonshapes.pt/lead'),

    // Token de autenticação da API de leads.
    'token' => env('CASAFARI_TOKEN'),

    // Identificador da origem do cliente (CustomerOriginID) atribuído pelo CRM.
    'customer_origin_id' => env('CASAFARI_CUSTOMER_ORIGIN_ID'),

    // Pedido HTTP ao feed: timeout em segundos e política de repetição.
    'feed_timeout' => (int) env('CASAFARI_FEED_TIMEOUT', 180),
    'feed_retries' => (int) env('CASAFARI_FEED_RETRIES', 3),
    'feed_retry_delay_ms' => (int) env('CASAFARI_FEED_RETRY_DELAY_MS', 5000),

    // Pasta (disco "local") onde se guardam amostras e o último feed descarregado.
    'storage_dir' => 'casafari',

];
