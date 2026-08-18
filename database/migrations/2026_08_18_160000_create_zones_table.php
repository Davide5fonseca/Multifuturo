<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| zones — texto editorial das páginas de zona (concelho / freguesia)
|--------------------------------------------------------------------------
|
| As zonas em si derivam da carteira ativa (city/locality dos imóveis). Esta
| tabela guarda apenas o conteúdo editorial opcional de cada uma — é o que
| distingue uma página de zona de uma listagem filtrada e gera tráfego de
| cauda longa. Sem linha aqui, a página existe na mesma, só sem texto.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('city_slug', 96);
            $table->string('locality_slug', 96)->nullable();
            $table->string('title', 160)->nullable();          // título editorial (senão, o nome da zona)
            $table->string('meta_description', 200)->nullable();
            $table->text('intro')->nullable();                 // parágrafo de abertura
            $table->text('body')->nullable();                  // texto longo (parágrafos separados por linha em branco)
            $table->string('cover_url', 2048)->nullable();     // fotografia da zona (local ou CDN próprio)
            $table->boolean('is_published')->default(true);
            $table->timestampsTz();

            $table->unique(['city_slug', 'locality_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
