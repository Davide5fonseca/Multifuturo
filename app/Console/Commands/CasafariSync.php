<?php

namespace App\Console\Commands;

use App\Events\PropertiesSynced;
use App\Services\Casafari\EmptyFeedException;
use App\Services\Casafari\FeedClient;
use App\Services\Casafari\PropertySyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * casafari:sync — sincroniza a carteira do CASAFARI para a tabela properties.
 *
 *   --force    reescreve todos os imóveis, ignorando o payload_hash
 *   --dry-run  percorre e conta, sem escrever nada na base de dados
 *   --file=    usa um XML local em vez de descarregar o feed (diagnóstico/testes)
 *
 * Um lock em cache impede duas execuções simultâneas (manual + agendada).
 */
class CasafariSync extends Command
{
    protected $signature = 'casafari:sync
        {--force : Reescrever todos os imóveis, ignorando o hash}
        {--dry-run : Não escrever nada; só contar}
        {--file= : Ler um XML local em vez de descarregar o feed}';

    protected $description = 'Sincroniza a carteira de imóveis do CASAFARI CRM para a base de dados local';

    public function handle(FeedClient $client, PropertySyncer $syncer): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $lock = Cache::lock('casafari:sync', 3600);

        if (! $lock->get()) {
            $this->components->warn('Já existe uma sincronização em curso. A sair.');

            return self::FAILURE;
        }

        try {
            $path = $this->option('file')
                ? $this->resolveFile((string) $this->option('file'))
                : $this->download($client);

            if ($path === null) {
                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Feed', $path);
            $this->components->twoColumnDetail('Modo', ($dryRun ? 'dry-run' : 'escrita').($force ? ' + force' : ''));

            $bar = $this->output->createProgressBar();
            $bar->setFormat(' %current% imóveis processados');
            $bar->start();

            $result = $syncer->sync($path, $force, $dryRun, fn () => $bar->advance());

            $bar->finish();
            $this->newLine(2);

            $this->components->twoColumnDetail('Vistos no feed', (string) $result->seen);
            $this->components->twoColumnDetail('Criados', (string) $result->created);
            $this->components->twoColumnDetail('Atualizados', (string) $result->updated);
            $this->components->twoColumnDetail('Inalterados (hash igual)', (string) $result->skipped);
            $this->components->twoColumnDetail('Desativados', (string) $result->deactivated.($result->deactivationSkipped ? ' (saltado: houve erros)' : ''));
            $this->components->twoColumnDetail('Erros', (string) $result->errors);
            $this->components->twoColumnDetail('Duração', number_format($result->seconds, 1).' s');

            foreach ($result->errorMessages as $message) {
                $this->components->warn($message);
            }

            Log::info('casafari:sync concluído', $result->toArray());

            if (! $dryRun) {
                PropertiesSynced::dispatch($result);
            }

            // Erros de mapeamento não impedem o resto, mas o exit code tem de os denunciar
            // para o scheduler notificar.
            return $result->errors > 0 ? self::FAILURE : self::SUCCESS;
        } catch (EmptyFeedException $e) {
            Log::error('casafari:sync abortado — feed vazio ou abaixo do mínimo', ['message' => $e->getMessage()]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            Log::error('casafari:sync falhou', ['exception' => $e]);
            $this->components->error(get_class($e).': '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function download(FeedClient $client): ?string
    {
        if (blank(config('casafari.feed_url'))) {
            $this->components->error('CASAFARI_FEED_URL não está definido no .env.');

            return null;
        }

        $path = null;
        $this->components->task('A descarregar o feed', function () use ($client, &$path): void {
            $path = $client->download();
        });

        return $path;
    }

    private function resolveFile(string $file): ?string
    {
        foreach ([$file, base_path($file), storage_path('app/'.$file)] as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        $this->components->error("Ficheiro não encontrado: {$file}");

        return null;
    }
}
