<?php

namespace App\Services\Casafari;

use App\Models\Property;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Motor de sincronização: percorre o feed, faz upsert por internal_id e, no
 * fim, desativa o que desapareceu. Nunca apaga linhas.
 *
 * Por imóvel:
 *  - sha256 do nó XML bruto; se igual ao payload_hash guardado (e sem --force),
 *    só atualiza synced_at/is_active e salta — evita reescrever milhares de
 *    linhas iguais a cada hora;
 *  - caso contrário, mapeia e faz updateOrCreate. O slug só é gerado na criação
 *    e nunca muda depois (URLs indexados).
 *
 * Guard crítico: se o feed devolver zero imóveis (ou menos de casafari.min_items)
 * lança EmptyFeedException ANTES de tocar em is_active.
 */
class PropertySyncer
{
    /** Acima disto, a desativação usa uma tabela temporária em vez de NOT IN com bindings. */
    private const NOT_IN_LIMIT = 30000;

    public function __construct(
        private readonly FeedReader $reader,
        private readonly PropertyMapper $mapper,
    ) {}

    /**
     * @param  Closure(int $seen): void|null  $onProgress  chamado a cada imóvel processado
     */
    public function sync(string $path, bool $force = false, bool $dryRun = false, ?Closure $onProgress = null): SyncResult
    {
        $result = new SyncResult($dryRun, $force);
        $startedAt = Carbon::now();
        $t0 = microtime(true);

        // Hashes já conhecidos: uma query, milhares de pares internal_id => hash — cabe em memória.
        $known = Property::query()->pluck('payload_hash', 'internal_id')->all();
        $seenIds = [];

        foreach ($this->reader->items($path) as $index => $xml) {
            $result->seen++;

            try {
                $this->syncOne($xml, $known, $seenIds, $result, $force, $dryRun, $startedAt);
            } catch (Throwable $e) {
                $result->addError(sprintf('#%d: %s', $index + 1, $e->getMessage()));
                Log::warning('casafari:sync — erro num imóvel', ['index' => $index + 1, 'error' => $e->getMessage()]);
            }

            if ($onProgress) {
                $onProgress($result->seen);
            }
        }

        // GUARD CRÍTICO — antes de qualquer desativação.
        $minItems = max(1, (int) config('casafari.min_items', 1));
        if ($result->seen < $minItems) {
            throw new EmptyFeedException(sprintf(
                'O feed devolveu %d imóveis (mínimo: %d). Nada foi desativado.',
                $result->seen,
                $minItems
            ));
        }

        $this->deactivateMissing($seenIds, $result, $dryRun, $startedAt);

        $result->seconds = microtime(true) - $t0;

        return $result;
    }

    /**
     * @param  array<string, string>  $known  internal_id => payload_hash existente
     * @param  array<string, true>  $seenIds  acumulador de internal_id vistos neste feed
     */
    private function syncOne(string $xml, array &$known, array &$seenIds, SyncResult $result, bool $force, bool $dryRun, Carbon $startedAt): void
    {
        $hash = hash('sha256', $xml);
        $data = $this->mapper->map($xml);
        $internalId = $data['internal_id'];

        if (isset($seenIds[$internalId])) {
            // O mesmo imóvel duas vezes no feed: a primeira ocorrência ganha.
            $result->addError("internal_id duplicado no feed: {$internalId}");

            return;
        }
        $seenIds[$internalId] = true;

        $exists = array_key_exists($internalId, $known);

        // Inalterado: só marca como visto.
        if ($exists && ! $force && hash_equals($known[$internalId], $hash)) {
            $result->skipped++;
            if (! $dryRun) {
                // toBase(): sem passar pelo Eloquent, para não mexer no updated_at — nada mudou no imóvel.
                Property::query()->where('internal_id', $internalId)->toBase()->update([
                    'synced_at' => $startedAt,
                    'is_active' => true,
                ]);
            }

            return;
        }

        $attributes = $data + [
            'payload_hash' => $hash,
            'synced_at' => $startedAt,
            'is_active' => true,
        ];

        if ($exists) {
            $result->updated++;
            if (! $dryRun) {
                // Slug NÃO está em $attributes: nunca é recalculado.
                Property::query()->where('internal_id', $internalId)->first()?->fill($attributes)->save();
            }

            return;
        }

        $result->created++;
        if (! $dryRun) {
            $attributes['slug'] = Property::generateSlug(
                $data['property_type'],
                $data['city'],
                $data['reference'],
                $internalId
            );
            Property::query()->create($attributes);
            $known[$internalId] = $hash;
        }
    }

    /**
     * Desativa os imóveis ativos cujo internal_id não apareceu neste feed
     * (semântica de conjunto: o feed é o snapshot completo). Se houve erros de
     * mapeamento, salta: não sabemos que imóveis falharam e desativar por engano
     * é pior do que deixar um imóvel a mais.
     *
     * @param  array<string, true>  $seenIds
     */
    private function deactivateMissing(array $seenIds, SyncResult $result, bool $dryRun, Carbon $startedAt): void
    {
        if ($result->errors > 0) {
            $result->deactivationSkipped = true;
            Log::warning('casafari:sync — desativação saltada por haver erros de mapeamento', ['errors' => $result->errors]);

            return;
        }

        $ids = array_keys($seenIds);
        $query = Property::query()->active();

        if (count($ids) <= self::NOT_IN_LIMIT) {
            $query->whereNotIn('internal_id', $ids);
        } else {
            // Carteira muito grande: tabela temporária em vez de milhares de bindings.
            DB::statement('CREATE TEMP TABLE IF NOT EXISTS casafari_seen_ids (internal_id varchar(64) PRIMARY KEY) ON COMMIT PRESERVE ROWS');
            DB::statement('TRUNCATE casafari_seen_ids');
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('casafari_seen_ids')->insert(array_map(fn ($id) => ['internal_id' => $id], $chunk));
            }
            $query->whereRaw('internal_id NOT IN (SELECT internal_id FROM casafari_seen_ids)');
        }

        if ($dryRun) {
            $result->deactivated = $query->count();

            return;
        }

        $result->deactivated = $query->update(['is_active' => false]);
    }
}
