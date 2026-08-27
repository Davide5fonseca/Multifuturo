<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| reference_prices — freguesia e origem do valor
|--------------------------------------------------------------------------
|
| locality: '' é o concelho inteiro; um nome é uma freguesia dentro dele (o
| INE publica o valor mediano das vendas por freguesia). Usa-se '' e não NULL
| para o índice único funcionar — no PostgreSQL dois NULL nunca são iguais.
| 191 caracteres porque o INE traz nomes como "União das freguesias de
| Cardosas, Vale de Cambra e São Miguel", que rebentam os 96 habituais.
|
| source: 'manual' (escrito no backoffice) ou 'ine' (importado). A importação
| nunca pisa o que é manual; editar uma linha no backoffice torna-a manual.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_prices', function (Blueprint $table) {
            $table->dropUnique(['city', 'property_type']);
            $table->string('locality', 191)->default('')->after('city');
            $table->string('source', 16)->default('manual')->after('notes');
            $table->unique(['city', 'locality', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::table('reference_prices', function (Blueprint $table) {
            $table->dropUnique(['city', 'locality', 'property_type']);
            $table->dropColumn(['locality', 'source']);
            $table->unique(['city', 'property_type']);
        });
    }
};
