<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Cópia de segurança: base de dados + ficheiros carregados.
 *
 * Corre todos os dias à hora de config('backup.at'). Guarda um .sql.gz com
 * tudo o que está na base de dados e um .tar.gz com as fotografias e os
 * documentos — os documentos vivem em disco privado e não estão em mais lado
 * nenhum.
 *
 * Restaurar (ver README): descomprimir e passar o .sql ao psql.
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run
        {--keep= : Dias de cópias a guardar (por omissão, config backup.keep_days)}';

    protected $description = 'Cópia de segurança da base de dados e dos ficheiros carregados';

    public function handle(): int
    {
        $inicio = microtime(true);
        $destino = rtrim((string) config('backup.path'), '/\\');
        $carimbo = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d_His');
        $pasta = $destino.DIRECTORY_SEPARATOR.$carimbo;

        File::ensureDirectoryExists($pasta);

        try {
            $sql = $this->dumpBaseDeDados($pasta);
            $ficheiros = $this->arquivarFicheiros($pasta);
        } catch (\Throwable $e) {
            // A pasta a meio não serve para nada e faria parecer que há cópia.
            File::deleteDirectory($pasta);
            $this->components->error('A cópia falhou: '.$e->getMessage());
            Log::error('Cópia de segurança falhou', ['erro' => $e->getMessage()]);

            return self::FAILURE;
        }

        $apagadas = $this->limparAntigas($destino);

        $this->components->info(sprintf(
            'Cópia de %s concluída em %.1fs — base de dados %s, ficheiros %s. %d antiga(s) apagada(s).',
            $carimbo,
            microtime(true) - $inicio,
            $this->tamanho($sql),
            $ficheiros ? $this->tamanho($ficheiros) : 'nenhum',
            $apagadas,
        ));

        return self::SUCCESS;
    }

    /** @return string caminho do .sql.gz */
    private function dumpBaseDeDados(string $pasta): string
    {
        $ligacao = config('database.default');
        $bd = config("database.connections.{$ligacao}");
        $ficheiro = $pasta.DIRECTORY_SEPARATOR.'base-de-dados.sql.gz';

        // --clean --if-exists: o ficheiro restaura por cima sem obrigar a
        // apagar a base de dados primeiro.
        //
        // O grep tira parâmetros de sessão que um pg_dump mais recente escreve
        // e um servidor mais antigo não conhece (transaction_timeout, do
        // PostgreSQL 17). São valores por omissão: retirá-los não muda nada, e
        // sem isso um restauro feito da forma segura (ON_ERROR_STOP) abortava
        // logo na primeira linha.
        $processo = Process::fromShellCommandline(
            'pg_dump --clean --if-exists --no-owner --no-privileges '
            .'--host=$PGHOST --port=$PGPORT --username=$PGUSER --dbname=$PGDATABASE '
            ."| grep -v '^SET transaction_timeout' "
            .'| gzip -9 > '.escapeshellarg($ficheiro)
        );
        $processo->setEnv([
            'PGHOST' => $bd['host'],
            'PGPORT' => (string) $bd['port'],
            'PGUSER' => $bd['username'],
            'PGPASSWORD' => $bd['password'],
            'PGDATABASE' => $bd['database'],
        ]);
        $processo->setTimeout(600);
        $processo->run();

        if (! $processo->isSuccessful() || ! File::exists($ficheiro) || File::size($ficheiro) < 100) {
            throw new \RuntimeException(trim($processo->getErrorOutput()) ?: 'pg_dump não produziu nada.');
        }

        return $ficheiro;
    }

    /** @return string|null caminho do .tar.gz, ou null se não houver ficheiros */
    private function arquivarFicheiros(string $pasta): ?string
    {
        // Fotografias (disco público) e documentos (disco privado, que não
        // estão em mais lado nenhum).
        $origens = array_filter([
            storage_path('app/public'),
            storage_path('app/private'),
        ], fn (string $p) => File::isDirectory($p) && count(File::allFiles($p)) > 0);

        if ($origens === []) {
            return null;
        }

        $ficheiro = $pasta.DIRECTORY_SEPARATOR.'ficheiros.tar.gz';
        $args = array_map(
            fn (string $p) => '-C '.escapeshellarg(dirname($p)).' '.escapeshellarg(basename($p)),
            $origens
        );

        $processo = Process::fromShellCommandline(
            'tar -czf '.escapeshellarg($ficheiro).' '.implode(' ', $args)
        );
        $processo->setTimeout(600);
        $processo->run();

        if (! $processo->isSuccessful()) {
            throw new \RuntimeException(trim($processo->getErrorOutput()) ?: 'tar falhou.');
        }

        return $ficheiro;
    }

    /** Apaga as cópias mais velhas do que o limite. @return int quantas foram */
    private function limparAntigas(string $destino): int
    {
        $dias = (int) ($this->option('keep') ?: config('backup.keep_days'));
        $limite = CarbonImmutable::now()->subDays(max(1, $dias));
        $apagadas = 0;

        foreach (File::directories($destino) as $pasta) {
            $nome = basename($pasta);

            // Só mexe no que tem o nosso formato de nome: nada de apagar por
            // engano uma pasta que alguém tenha posto aqui.
            if (! preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $nome)) {
                continue;
            }

            if (CarbonImmutable::createFromFormat('Y-m-d_His', $nome)->lt($limite)) {
                File::deleteDirectory($pasta);
                $apagadas++;
            }
        }

        return $apagadas;
    }

    private function tamanho(string $ficheiro): string
    {
        $bytes = File::size($ficheiro);

        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
