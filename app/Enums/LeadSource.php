<?php

namespace App\Enums;

/** Formulário de origem da lead. */
enum LeadSource: string
{
    case Property = 'property';     // pedido de informação sobre um imóvel
    case Contact = 'contact';       // contacto geral
    case Valuation = 'valuation';   // "Quanto vale a minha casa?"
}
