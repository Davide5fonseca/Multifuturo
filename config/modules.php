<?php

/*
|--------------------------------------------------------------------------
| Módulos — o que aparece na página de escolha do portal
|--------------------------------------------------------------------------
|
| Cada módulo é uma área com entrada própria. Acrescentar um módulo novo é:
|   1. criar a área (um painel do Filament, ou outra aplicação com um URL);
|   2. registá-lo aqui, com a chave que vai identificar os acessos;
|   3. dar acesso às pessoas certas (Backoffice → Equipa → Módulos).
|
| Os administradores veem todos os módulos ativos; os restantes só aqueles
| a que lhes foi dado acesso (tabela module_access) — e os módulos
| 'public', que qualquer conta vê.
|
|   key         identificador estável (é o que fica gravado nos acessos)
|   name        nome no cartão
|   description uma linha a explicar o que lá se faz
|   icon        ícone do cartão (resources/views/portal/icons/{icon}.blade.php)
|   route       nome da rota de entrada (com 'params' se precisar), ou 'url'
|   panel       id do painel do Filament que este módulo protege, se houver
|   public      true: visível a qualquer conta ativa, sem acesso explícito
|   new_tab     true: abre noutro separador (para sair do portal sem o perder)
|   order       posição na página
|   active      false esconde o módulo sem apagar os acessos
|
*/

return [

    'site' => [
        'name' => 'Site',
        'description' => 'O website público da agência, tal como os clientes o veem.',
        'icon' => 'globe',
        'route' => 'home',
        'params' => ['locale' => 'pt'],
        'panel' => null,
        'public' => true,
        'new_tab' => true,
        'order' => 1,
        'active' => true,
    ],

    'backoffice' => [
        'name' => 'Backoffice',
        'description' => 'Fichas de imóveis, dúvidas dos clientes, contactos, calendário, zonas, valores de referência, alertas e equipa.',
        'icon' => 'backoffice',
        'route' => 'filament.admin.pages.dashboard',
        'panel' => 'admin',
        'public' => false,
        'new_tab' => false,
        'order' => 2,
        'active' => true,
    ],

];
