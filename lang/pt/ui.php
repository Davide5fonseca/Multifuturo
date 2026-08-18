<?php

/*
|--------------------------------------------------------------------------
| Strings da interface (pt-PT)
|--------------------------------------------------------------------------
|
| Todo o texto estático da UI vive aqui, nunca solto nos Blades. Um segundo
| idioma é um novo ficheiro lang/xx/ui.php — não uma reescrita das views.
|
*/

return [

    'skip_to_content' => 'Saltar para o conteúdo',

    'nav' => [
        'buy' => 'Comprar',
        'rent' => 'Arrendar',
        'zones' => 'Zonas',
        'valuation' => 'Quanto vale a minha casa?',
        'about' => 'A agência',
        'contact' => 'Contactos',
        'favorites' => 'Favoritos',
        'menu_open' => 'Abrir menu',
        'menu_close' => 'Fechar menu',
        'main' => 'Navegação principal',
    ],

    'footer' => [
        'agency' => 'A agência',
        'properties' => 'Imóveis',
        'legal' => 'Legal',
        'ami' => 'Licença AMI :number',
        'ami_missing' => 'Licença AMI: por atribuir',
        'complaints_book' => 'Livro de Reclamações eletrónico',
        'privacy' => 'Política de privacidade',
        'terms' => 'Termos e condições',
        'cookies' => 'Política de cookies',
        'rights' => '© :year :name. Todos os direitos reservados.',
        'follow' => 'Siga-nos',
    ],

    'search' => [
        'title' => 'Procurar imóvel',
        'placeholder' => 'Concelho, freguesia ou referência',
        'submit' => 'Procurar',
        'business_type' => 'Finalidade',
        'buy' => 'Comprar',
        'rent' => 'Arrendar',
    ],

    'errors' => [
        '404_title' => 'Página não encontrada',
        '404_lead' => 'O endereço que procura não existe ou o imóvel já não está disponível. Experimente pesquisar a carteira.',
        '404_back' => 'Voltar à página inicial',
        '500_title' => 'Ocorreu um erro',
        '500_lead' => 'Algo correu mal do nosso lado. Já fomos avisados; tente novamente dentro de instantes.',
        '503_title' => 'Em manutenção',
        '503_lead' => 'Estamos a fazer uma atualização rápida. Volte dentro de alguns minutos.',
    ],

    'home' => [
        'title' => 'Imóveis para comprar e arrendar',
        'eyebrow' => 'Mediação imobiliária',
        'lead' => 'A carteira da Multifuturo, atualizada diariamente.',
    ],

    'lead' => [
        'title_property' => 'Pedir informação',
        'title_contact' => 'Fale connosco',
        'title_valuation' => 'Quanto vale a minha casa?',
        'lead_property' => 'Envie-nos o seu pedido e um consultor entrará em contacto.',
        'lead_contact' => 'Deixe-nos a sua mensagem e respondemos o mais depressa possível.',
        'lead_valuation' => 'Diga-nos onde fica e como é o seu imóvel. Preparamos uma avaliação sem compromisso.',
        'name' => 'Nome',
        'email' => 'Email',
        'phone' => 'Telefone',
        'message' => 'Mensagem',
        'message_property' => 'Gostaria de receber mais informação sobre o imóvel :reference.',
        'address' => 'Morada do imóvel',
        'city' => 'Concelho',
        'property_type' => 'Tipo de imóvel',
        'bedrooms' => 'Tipologia',
        'area' => 'Área (m²)',
        'condition' => 'Estado de conservação',
        'consent_contact' => 'Autorizo a Multifuturo Imóveis a contactar-me sobre este pedido.',
        'consent_marketing' => 'Quero receber novidades e imóveis selecionados por email.',
        'privacy_notice' => 'Os dados são tratados pela :name apenas para responder ao seu pedido, conforme a :link.',
        'privacy_link' => 'política de privacidade',
        'submit' => 'Enviar pedido',
        'success' => 'Recebemos o seu pedido. Obrigado — entraremos em contacto em breve.',
        'error' => 'Verifique os campos assinalados.',
        'optional' => 'opcional',
    ],

    'business' => [
        'sale' => 'Venda',
        'rent' => 'Arrendamento',
    ],

    'listing' => [
        'buy_title' => 'Imóveis para comprar',
        'rent_title' => 'Imóveis para arrendar',
        'coming_soon' => 'A carteira está a ser preparada. Em breve poderá pesquisar todos os imóveis aqui.',
    ],

];
