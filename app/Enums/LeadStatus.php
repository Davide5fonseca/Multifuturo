<?php

namespace App\Enums;

/** Estado do envio da lead para o CRM. */
enum LeadStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
