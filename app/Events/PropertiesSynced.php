<?php

namespace App\Events;

use App\Services\Casafari\SyncResult;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado no fim de um sync real (não em --dry-run). A Fase 4 ouve-o para
 * invalidar a cache das listagens em Redis.
 */
class PropertiesSynced
{
    use Dispatchable;

    public function __construct(public readonly SyncResult $result) {}
}
