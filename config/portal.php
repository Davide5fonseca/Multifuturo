<?php

/*
|--------------------------------------------------------------------------
| Portal — entrada única e escolha de módulo
|--------------------------------------------------------------------------
|
| A equipa entra uma vez (/entrar) e aterra numa página de cartões (/portal)
| com os módulos a que tem acesso. O backoffice de imóveis (/admin) é o
| primeiro módulo; os próximos são novos painéis do Filament (ou outra
| aplicação) registados em config/modules.php.
|
| Verificação em duas etapas por email: depois da palavra-passe, um código de
| seis algarismos enviado para o email da conta. Mesmas regras do Nexus
| Portal (validade, tentativas, intervalo de reenvio).
|
*/

return [

    // Segunda etapa por email ligada? Em desenvolvimento os códigos chegam ao Mailpit.
    'mfa' => (bool) env('PORTAL_MFA', true),

    // Minutos de validade de um código.
    'code_minutes' => 10,

    // Tentativas erradas antes de o código ficar queimado.
    'max_attempts' => 5,

    // Segundos entre pedidos de novo código.
    'resend_seconds' => 60,

    // Tentativas de login falhadas por email+IP antes de esperar um minuto.
    'login_attempts' => 5,

];
