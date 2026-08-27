<?php

namespace App\Console\Commands;

use App\Models\ReferencePrice;
use App\Support\PropertyCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Enche a tabela de valores de referência (€/m²) a partir da API do INE.
 *
 * Duas séries (ver config/valuation.php):
 *   - avaliação bancária por concelho e tipo (apartamentos / moradias): é a
 *     primeira escolha porque separa o tipo, que pesa muito no valor;
 *   - vendas dos últimos 12 meses por concelho e freguesia: sem tipo, mas
 *     cobre quase todos os concelhos (a avaliação bancária é confidencial
 *     nos pequenos) e dá o valor por freguesia — a "zona".
 *
 * Regras: um concelho recebe, por tipo, a avaliação bancária se existir,
 * senão o valor das vendas; cada freguesia com vendas recebe esse valor para
 * apartamentos e moradias. Terrenos nunca vêm do INE. Linhas escritas à mão
 * no backoffice (source = manual) não são tocadas.
 *
 * Corre à segunda-feira de madrugada (routes/console.php) e a partir do
 * botão "Importar do INE" no backoffice.
 */
class ValuationImportIne extends Command
{
    protected $signature = 'valuation:import-ine';

    protected $description = 'Importa do INE os valores de referência por m² (concelhos e freguesias)';

    /** Tipo de construção do INE → tipo do simulador. 'T' (total) fica de fora. */
    private const APPRAISAL_TYPES = ['1' => 'apartment', '2' => 'house'];

    /** Comprimento do código geográfico do INE: 7 = município, 9 = freguesia. */
    private const MUNICIPALITY = 7;

    private const PARISH = 9;

    public function handle(): int
    {
        try {
            $appraisal = $this->fetch((string) config('valuation.ine.appraisal'));
            $sales = $this->fetch((string) config('valuation.ine.sales'));
        } catch (\Throwable $e) {
            Log::error('Importação do INE falhou: '.$e->getMessage());
            $this->error('Não foi possível obter os dados do INE: '.$e->getMessage());

            return self::FAILURE;
        }

        $municipalities = []; // geocod → nome
        $salesByCity = [];    // geocod → €/m²
        $salesByParish = [];  // geocod (9) → [nome, €/m²]
        $appraisalByCity = []; // geocod → [tipo → €/m²]

        foreach ($sales['rows'] as $row) {
            if (($row['dim_3'] ?? null) !== 'T' || ! isset($row['valor'])) {
                continue;
            }

            $code = (string) $row['geocod'];

            if (strlen($code) === self::MUNICIPALITY) {
                $municipalities[$code] = trim((string) $row['geodsg']);
                $salesByCity[$code] = (float) $row['valor'];
            } elseif (strlen($code) === self::PARISH) {
                $salesByParish[$code] = [trim((string) $row['geodsg']), (float) $row['valor']];
            }
        }

        foreach ($appraisal['rows'] as $row) {
            $code = (string) $row['geocod'];
            $type = self::APPRAISAL_TYPES[$row['dim_3'] ?? ''] ?? null;

            if (strlen($code) !== self::MUNICIPALITY || ! $type || ! isset($row['valor'])) {
                continue;
            }

            $municipalities[$code] ??= trim((string) $row['geodsg']);
            $appraisalByCity[$code][$type] = (float) $row['valor'];
        }

        $rows = [];
        $now = now();
        $add = function (string $city, string $locality, string $type, float $value, string $notes) use (&$rows, $now) {
            $rows[$city.'|'.$locality.'|'.$type] = [
                'city' => $city,
                'locality' => $locality,
                'property_type' => $type,
                'price_per_m2' => $value,
                'notes' => $notes,
                'source' => 'ine',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        foreach ($municipalities as $code => $city) {
            foreach (['apartment', 'house'] as $type) {
                if (isset($appraisalByCity[$code][$type])) {
                    $add($city, '', $type, $appraisalByCity[$code][$type], 'INE — valor mediano de avaliação bancária, '.$appraisal['period']);
                } elseif (isset($salesByCity[$code])) {
                    $add($city, '', $type, $salesByCity[$code], 'INE — valor mediano das vendas nos últimos 12 meses, '.$sales['period']);
                }
            }
        }

        $parishes = 0;

        foreach ($salesByParish as $code => [$locality, $value]) {
            $city = $municipalities[substr($code, 0, self::MUNICIPALITY)] ?? null;

            if (! $city) {
                continue;
            }

            $parishes++;

            foreach (['apartment', 'house'] as $type) {
                $add($city, $locality, $type, $value, 'INE — valor mediano das vendas nos últimos 12 meses (freguesia), '.$sales['period']);
            }
        }

        // O que a agência escreveu à mão vale mais do que a estatística.
        $manual = ReferencePrice::query()->where('source', 'manual')->get()
            ->map(fn (ReferencePrice $r) => $r->city.'|'.$r->locality.'|'.$r->property_type)
            ->flip();

        $rows = array_values(array_diff_key($rows, $manual->all()));

        foreach (array_chunk($rows, 500) as $chunk) {
            ReferencePrice::query()->upsert(
                $chunk,
                ['city', 'locality', 'property_type'],
                ['price_per_m2', 'notes', 'source', 'updated_at'],
            );
        }

        // upsert não dispara eventos do modelo: limpar a cache à mão.
        PropertyCache::flush();

        $this->info(sprintf(
            'INE: %d valores importados (%d concelhos, %d freguesias); %d escritos à mão preservados. Avaliação bancária: %s · Vendas: %s.',
            count($rows), count($municipalities), $parishes, $manual->count(), $appraisal['period'], $sales['period'],
        ));

        return self::SUCCESS;
    }

    /**
     * Último período publicado de um indicador, tal como o INE o devolve.
     *
     * @return array{period: string, rows: list<array<string, mixed>>}
     */
    private function fetch(string $indicator): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => config('app.name').' (valuation:import-ine)',
        ])
            ->timeout(90)
            ->retry(2, 2000, throw: false)
            ->get((string) config('valuation.ine.url'), ['op' => 2, 'varcd' => $indicator, 'lang' => 'PT'])
            ->throw();

        $payload = $response->json()[0] ?? null;

        if (! is_array($payload) || empty($payload['Dados']) || ! is_array($payload['Dados'])) {
            throw new RuntimeException("resposta sem dados para o indicador {$indicator}");
        }

        $period = (string) ($payload['UltimoPref'] ?? array_key_first($payload['Dados']));
        $rows = array_values($payload['Dados'])[0];

        if (! is_array($rows)) {
            throw new RuntimeException("resposta inesperada para o indicador {$indicator}");
        }

        return ['period' => $period, 'rows' => $rows];
    }
}
