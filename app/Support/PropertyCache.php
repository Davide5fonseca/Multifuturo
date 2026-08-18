<?php

namespace App\Support;

use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache das leituras de imóveis (listagens, filtros, destaques, zonas, sitemap).
 * Tudo debaixo da mesma tag para ser invalidado de uma vez no fim do sync
 * (ver FlushPropertyCache). Redis e array suportam tags; ficheiro/BD não —
 * nesse caso cai para cache sem tags (invalidação por TTL apenas).
 */
final class PropertyCache
{
    public const TAG = 'properties';

    /** TTL por defeito: 1 h (o sync corre de hora a hora e limpa mais cedo se houver mudanças). */
    public const TTL = 3600;

    public static function store(): TaggedCache|Repository
    {
        $repo = Cache::store();

        return $repo->supportsTags() ? $repo->tags([self::TAG]) : $repo;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, \Closure $callback, ?int $ttl = null)
    {
        return self::store()->remember('props:'.$key, $ttl ?? self::TTL, $callback);
    }

    public static function flush(): void
    {
        $repo = Cache::store();

        if ($repo->supportsTags()) {
            $repo->tags([self::TAG])->flush();
        }
    }
}
