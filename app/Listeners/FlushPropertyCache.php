<?php

namespace App\Listeners;

use App\Events\PropertiesSynced;
use App\Support\PropertyCache;

/**
 * No fim de cada sync real, invalida a cache das listagens — só se algo mudou
 * (criados/atualizados/desativados); um sync sem alterações não esvazia nada.
 */
class FlushPropertyCache
{
    public function handle(PropertiesSynced $event): void
    {
        $r = $event->result;

        if ($r->created + $r->updated + $r->deactivated > 0 || $r->force) {
            PropertyCache::flush();
        }
    }
}
