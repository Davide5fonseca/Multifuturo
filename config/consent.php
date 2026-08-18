<?php

/*
|--------------------------------------------------------------------------
| Consentimento de cookies
|--------------------------------------------------------------------------
|
| O banner é próprio (sem CMP de terceiros). As escolhas ficam num cookie
| first-party em JSON: {"v":1,"necessary":true,"analytics":false,"marketing":false,"ts":…}.
| Scripts não essenciais são escritos como <script type="text/plain"
| data-consent="analytics|marketing"> e só são ativados pelo JS depois do opt-in
| da respetiva categoria (ver resources/js/consent.js e <x-consent-script>).
|
*/

return [

    // Nome do cookie de preferências (first-party, SameSite=Lax, 6 meses).
    'cookie' => env('CONSENT_COOKIE', 'mf_consent'),

    // Dias de validade das escolhas; passado este prazo o banner volta a aparecer.
    'days' => (int) env('CONSENT_DAYS', 180),

    // Versão das categorias/textos: ao mudar, o consentimento anterior deixa de valer.
    'version' => (int) env('CONSENT_VERSION', 1),

    // Categorias opcionais (as "necessary" estão sempre ativas).
    'categories' => ['analytics', 'marketing'],

];
