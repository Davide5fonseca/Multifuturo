<?php

/*
|--------------------------------------------------------------------------
| Dados da agência
|--------------------------------------------------------------------------
|
| Identificação comercial da Multifuturo Propriedades usada em rodapé, meta
| tags, JSON-LD e emails. Tudo vem do .env.
|
| O número AMI é OBRIGATÓRIO por lei em toda a comunicação comercial de
| mediação imobiliária (Lei n.º 15/2013). Existe um teste que falha se
| estiver vazio em produção — não é um TODO, é uma não-conformidade.
|
*/

return [

    'name' => env('AGENCY_NAME', 'Multifuturo Propriedades'),

    // Licença AMI (ex.: "AMI 12345"). Só o número; o prefixo é aplicado na view.
    'ami' => env('AGENCY_AMI'),

    'phone' => env('AGENCY_PHONE'),
    'email' => env('AGENCY_EMAIL'),
    'address' => env('AGENCY_ADDRESS'),

    'social' => [
        'facebook' => env('AGENCY_FACEBOOK'),
        'instagram' => env('AGENCY_INSTAGRAM'),
        'linkedin' => env('AGENCY_LINKEDIN'),
    ],

    // Livro de Reclamações eletrónico — obrigatório no rodapé.
    'complaints_book_url' => env('AGENCY_COMPLAINTS_BOOK_URL', 'https://www.livroreclamacoes.pt/'),

    // Versão da política de privacidade em vigor. Gravada em cada lead (RGPD: prova do texto apresentado).
    // Atualizar sempre que o texto da política mudar.
    'privacy_policy_version' => env('AGENCY_PRIVACY_POLICY_VERSION', '2026-08-24'),

    // Fotografia do hero da homepage (URL local, ex.: /images/hero.jpg). Sem valor, usa a capa do primeiro destaque.
    'hero_image' => env('AGENCY_HERO_IMAGE'),

    /*
    |--------------------------------------------------------------------------
    | Fotografias da abertura
    |--------------------------------------------------------------------------
    |
    | Alternam de 5 em 5 segundos, por ordem sorteada em cada visita. São
    | fotografias de arquivo (Unsplash, licença que permite uso comercial sem
    | atribuição), guardadas no nosso servidor — nenhum pedido a terceiros.
    |
    | SÃO DECORATIVAS: não são imóveis da agência. Quando houver fotografia
    | própria da carteira ou da região, é trocar os ficheiros em
    | public/images/hero/ (ou esta lista) e fica feito.
    |
    */
    'hero_images' => [
        '/images/hero/hero-1.webp',
        '/images/hero/hero-2.webp',
        '/images/hero/hero-3.webp',
        '/images/hero/hero-4.webp',
        '/images/hero/hero-5.webp',
        '/images/hero/hero-6.webp',
    ],

];
