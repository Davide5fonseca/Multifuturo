<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Reciclagem dos imóveis
|--------------------------------------------------------------------------
|
| Apagar um imóvel era definitivo: a ficha, o histórico e as visualizações
| desapareciam sem recurso. Um clique errado custava o trabalho todo de uma
| angariação, e não há cópias de segurança para lá ir buscar.
|
| Passa a haver reciclagem: apagar marca a data, o site deixa de mostrar o
| imóvel de imediato, e a ficha pode ser reposta. Apagar de vez continua a
| existir, mas é um segundo passo consciente.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
