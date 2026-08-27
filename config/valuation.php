<?php

/*
|--------------------------------------------------------------------------
| Estimativa imediata — fontes de dados
|--------------------------------------------------------------------------
|
| O simulador "Quanto vale a minha casa?" usa €/m² por concelho (e freguesia)
| guardados em reference_prices. O comando valuation:import-ine enche essa
| tabela a partir da API pública do INE (gratuita, sem chave):
|
|   appraisal  Valor mediano de avaliação bancária (€/m²) por município e tipo
|              de construção (apartamentos / moradias). Mensal. Confidencial
|              nos concelhos com poucas avaliações.
|   sales      Valor mediano das vendas de alojamentos familiares nos últimos
|              12 meses (€/m²) por município E freguesia. Trimestral. Cobre
|              praticamente todos os concelhos, sem separar o tipo.
|
| Os códigos são os do INE em 2026; se o INE reformular a série, basta trocar
| aqui (ou no .env) sem tocar no código.
|
*/

return [

    'ine' => [
        'url' => 'https://www.ine.pt/ine/json_indicador/pindica.jsp',
        'appraisal' => env('INE_APPRAISAL_INDICATOR', '0012248'),
        'sales' => env('INE_SALES_INDICATOR', '0012246'),
    ],

];
