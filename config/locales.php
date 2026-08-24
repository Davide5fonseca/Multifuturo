<?php

/*
|--------------------------------------------------------------------------
| Idiomas do site
|--------------------------------------------------------------------------
|
| O site é multilingue: cada idioma tem o seu prefixo no endereço
| (/pt/comprar, /en/comprar) e o seu ficheiro em lang/{idioma}/ui.php.
|
| Acrescentar um idioma é: pô-lo em APP_LOCALES, criar lang/{idioma}/ui.php e
| traduzir. Nada nas views muda — o texto nunca está solto nos Blades.
|
| Um idioma listado em "available" mas fora de APP_LOCALES está preparado mas
| desligado: não tem rotas, não aparece no seletor nem nas alternativas
| hreflang, e não entra no sitemap.
|
*/

return [

    // Idioma por omissão: é para aqui que "/" reencaminha.
    'default' => env('APP_LOCALE', 'pt'),

    // Idiomas ligados, por ordem de apresentação no seletor.
    'enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_LOCALES', 'pt'))
    ))),

    // Todos os idiomas previstos. 'html' é o valor do atributo lang="" e do hreflang.
    'available' => [
        'pt' => ['label' => 'Português', 'short' => 'PT', 'html' => 'pt-PT'],
        'en' => ['label' => 'English', 'short' => 'EN', 'html' => 'en'],
        'fr' => ['label' => 'Français', 'short' => 'FR', 'html' => 'fr'],
        'de' => ['label' => 'Deutsch', 'short' => 'DE', 'html' => 'de'],
    ],

];
