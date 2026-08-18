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

    // EntityType enviado com cada lead. Valor A CONFIRMAR com a documentação da API de leads.
    'lead_entity_type' => env('CASAFARI_LEAD_ENTITY_TYPE', 'Lead'),

    // Pedido HTTP ao feed: timeout em segundos e política de repetição.
    'feed_timeout' => (int) env('CASAFARI_FEED_TIMEOUT', 180),
    'feed_retries' => (int) env('CASAFARI_FEED_RETRIES', 3),
    'feed_retry_delay_ms' => (int) env('CASAFARI_FEED_RETRY_DELAY_MS', 5000),

    // Pasta (disco "local") onde se guardam amostras e o último feed descarregado.
    'storage_dir' => 'casafari',

    // Email que recebe o output do sync quando falha (scheduler). Vazio = sem email.
    'alert_email' => env('CASAFARI_ALERT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Salvaguardas do sync
    |--------------------------------------------------------------------------
    |
    | min_items: número mínimo de imóveis que o feed tem de devolver para que o
    | sync toque em is_active. Com 0 imóveis aborta SEMPRE (guard crítico); este
    | valor permite ser mais exigente (ex.: 50) quando se conhecer o volume real,
    | para que um feed truncado não desative meia carteira.
    |
    */
    'min_items' => (int) env('CASAFARI_MIN_ITEMS', 1),

    /*
    |--------------------------------------------------------------------------
    | Estrutura do feed — PROVISÓRIO
    |--------------------------------------------------------------------------
    |
    | ATENÇÃO: os nomes de nós abaixo são um palpite de trabalho para que o motor
    | de sync exista e seja testado. NÃO saíram do feed real (o URL ainda não foi
    | fornecido). Quando o `casafari:inspect` correr sobre o feed verdadeiro, este
    | bloco é corrigido — e só este bloco: o motor não depende dos nomes.
    |
    | Sintaxe dos caminhos (App\Services\Casafari\PropertyMapper):
    |   'Nome'            elemento filho direto
    |   'A/B'             elemento aninhado
    |   '@attr'           atributo do próprio nó
    |   'A/@attr'         atributo de um filho
    |
    */
    'feed' => [
        // Nome do elemento que representa UM imóvel.
        'item_node' => 'Property',

        // Idioma das traduções: 'attribute' (ex.: <Title lang="pt">) ou 'element' (ex.: <Language>pt</Language>).
        'lang_mode' => 'attribute',
        'lang_name' => 'lang',
        'default_locale' => 'pt',

        // Elemento(s) a IGNORAR por privacidade — nunca lidos, nunca persistidos.
        'ignored_nodes' => ['Owner'],
    ],

    'mapping' => [
        // Colunas simples: coluna => caminho.
        'fields' => [
            'internal_id' => 'ID',
            'reference' => 'Reference',
            'price' => 'Price',
            'currency' => 'Currency',
            'business_type' => 'BusinessType',
            'property_type' => 'PropertyType',
            'property_condition' => 'Condition',
            'bedrooms' => 'Bedrooms',
            'bathrooms' => 'Bathrooms',
            'house_area' => 'HouseArea',
            'plot_area' => 'PlotArea',
            'gross_area' => 'GrossArea',
            'country' => 'Location/Country',
            'district' => 'Location/District',
            'city' => 'Location/City',
            'locality' => 'Location/Locality',
            'zone' => 'Location/Zone',
            'zipcode' => 'Location/ZipCode',
            'lat' => 'Location/Latitude',
            'lon' => 'Location/Longitude',
            'gmap_visible' => 'Location/GmapVisible',
            'floor_number' => 'Floor',
            'build_year' => 'BuildYear',
            'energy_rating' => 'EnergyRating',
            'crm_property_url' => 'Url',
            'video_url' => 'VideoUrl',
            'virtual_tour_url' => 'VirtualTourUrl',
            'floorplan_url' => 'FloorplanUrl',
            'crm_updated_at' => 'UpdatedAt',
            'is_exclusive' => 'Exclusive',
            'is_featured' => 'Featured',
        ],

        // Traduções: elementos com o idioma no atributo/elemento indicado em feed.lang_*.
        'translations' => [
            'title' => 'Title',
            'description' => 'Description',
        ],

        // Listas.
        'photos' => ['container' => 'Photos', 'item' => 'Photo', 'url' => null, 'order' => '@order'],
        'features' => ['container' => 'Features', 'item' => 'Feature'],

        // Broker: só nome e foto. Contactos do consultor NÃO se guardam sem autorização expressa.
        'broker' => ['name' => 'Broker/Name', 'photo' => 'Broker/Photo'],

        // Normalização de valores do feed para os valores internos.
        'business_type_map' => [
            'sale' => 'sale', 'venda' => 'sale', 'sell' => 'sale', 'buy' => 'sale', 'comprar' => 'sale',
            'rent' => 'rent', 'arrendamento' => 'rent', 'arrendar' => 'rent', 'rental' => 'rent', 'lease' => 'rent',
        ],
        'truthy' => ['1', 'true', 'yes', 'sim', 'y', 's'],
    ],

];
