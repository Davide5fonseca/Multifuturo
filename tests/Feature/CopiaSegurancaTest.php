<?php

/*
 * Cópias de segurança. Uma cópia que não se sabe restaurar não é uma cópia:
 * estes testes verificam que o ficheiro é produzido, que contém mesmo os
 * dados, e que as antigas são limpas.
 */

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->pasta = storage_path('backups-teste');
    File::deleteDirectory($this->pasta);
    config(['backup.path' => $this->pasta]);
});

afterEach(fn () => File::deleteDirectory($this->pasta));

it('a cópia guarda a estrutura e os dados de todas as tabelas', function () {
    $this->artisan('backup:run')->assertSuccessful();

    $copias = File::directories($this->pasta);
    expect($copias)->toHaveCount(1);

    $sql = $copias[0].'/base-de-dados.sql.gz';
    expect(File::exists($sql))->toBeTrue()
        ->and(File::size($sql))->toBeGreaterThan(100);

    $conteudo = gzdecode(File::get($sql));

    // Estrutura e blocos de dados de cada tabela que interessa. As linhas em
    // si não dá para verificar aqui: os testes correm dentro de uma transação
    // e o pg_dump liga-se por fora, logo não vê o que ainda não foi
    // confirmado. O restauro real está verificado à mão e descrito no README.
    expect($conteudo)->toContain('CREATE TABLE')
        ->toContain('COPY public.properties')
        ->toContain('COPY public.leads')
        ->toContain('COPY public.users')
        // --clean --if-exists: restaura por cima sem apagar a base primeiro.
        ->toContain('DROP TABLE IF EXISTS')
        // A linha que um servidor mais antigo não entende foi retirada, senão
        // um restauro com ON_ERROR_STOP abortava logo.
        ->not->toContain('SET transaction_timeout');
});

it('a pasta tem o carimbo da data e da hora', function () {
    $this->artisan('backup:run')->assertSuccessful();

    expect(basename(File::directories($this->pasta)[0]))
        ->toMatch('/^\d{4}-\d{2}-\d{2}_\d{6}$/');
});

it('as cópias antigas são apagadas e as recentes ficam', function () {
    // Cópias falsas: uma de ontem, uma de há muito tempo. Os nomes ficam em
    // variáveis — gerá-los outra vez na verificação dava outro segundo.
    $ontem = now()->subDay()->format('Y-m-d_His');
    $antiga = now()->subDays(90)->format('Y-m-d_His');

    File::ensureDirectoryExists($this->pasta.'/'.$ontem);
    File::ensureDirectoryExists($this->pasta.'/'.$antiga);
    // E uma pasta que não é nossa: não pode ser tocada.
    File::ensureDirectoryExists($this->pasta.'/guardar-isto');

    $this->artisan('backup:run', ['--keep' => 14])->assertSuccessful();

    $nomes = array_map('basename', File::directories($this->pasta));

    expect($nomes)->toContain('guardar-isto')
        ->toContain($ontem)
        ->not->toContain($antiga);
});

it('está agendada uma cópia diária, sempre à mesma hora', function () {
    config(['backup.at' => '03:30']);

    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'backup:run'));

    expect($eventos)->toHaveCount(1)
        // Todos os dias às 03:30 — minuto 30, hora 3.
        ->and($eventos->first()->expression)->toBe('30 3 * * *');
});
