<?php

/*
|--------------------------------------------------------------------------
| Módulos — o que aparece na página de escolha do portal
|--------------------------------------------------------------------------
|
| Cada módulo é uma área de trabalho com entrada própria. Acrescentar um
| módulo novo é:
|   1. criar a área (um painel do Filament, ou outra aplicação com um URL);
|   2. registá-lo aqui, com a chave que vai identificar os acessos;
|   3. dar acesso às pessoas certas (backoffice → Equipa → Módulos).
|
| Os administradores veem todos os módulos ativos; os restantes só aqueles
| a que lhes foi dado acesso (tabela module_access).
|
|   key         identificador estável (é o que fica gravado nos acessos)
|   name        nome no cartão
|   description uma linha a explicar o que lá se faz
|   icon        ícone do cartão (resources/views/portal/icons/{icon}.blade.php)
|   route       nome da rota de entrada (ou 'url' com um endereço completo)
|   panel       id do painel do Filament que este módulo protege, se houver
|   order       posição na página
|   active      false esconde o módulo sem apagar os acessos
|
*/

return [

    'imoveis' => [
        'name' => 'Imóveis',
        'description' => 'Fichas de imóveis, dúvidas dos clientes, contactos, calendário, zonas, valores de referência e alertas.',
        'icon' => 'home',
        'route' => 'filament.admin.pages.dashboard',
        'panel' => 'admin',
        'order' => 1,
        'active' => true,
    ],

];
