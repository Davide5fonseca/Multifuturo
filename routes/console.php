<?php

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| Sem ligação ao CRM (decisão de 2026-08-19), não há sincronização agendada:
| a carteira é gerida no backoffice (/admin) e escrita diretamente na base de
| dados. O comando casafari:sync mantém-se disponível apenas para importações
| pontuais de ficheiro (casafari:sync --file=…), executadas à mão.
|
| Se voltar a existir um feed automático, reativar aqui o bloco de agendamento
| (hourlyAt + withoutOverlapping + emailOutputOnFailure) — ver o histórico do
| ficheiro no git (Fase 3).
|
*/
