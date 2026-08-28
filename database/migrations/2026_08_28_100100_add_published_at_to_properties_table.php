<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| properties.published_at — quando a ficha apareceu no site pela 1.ª vez
|--------------------------------------------------------------------------
|
| Os alertas de imóveis precisam de saber o que é "novo" desde o último
| envio. updated_at não serve (muda com qualquer edição) e is_active é um
| estado, não uma data. O observer escreve published_at na primeira vez
| que a ficha fica publicável e nunca mais lhe toca.
|
| As fichas já publicadas ficam com a data que têm: nenhum alerta criado a
| partir de agora as vai considerar novas.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->timestampTz('published_at')->nullable()->after('is_featured');
            $table->index('published_at');
        });

        DB::statement(<<<'SQL'
            UPDATE properties
            SET published_at = COALESCE(crm_updated_at, created_at)
            WHERE is_active AND NOT is_sold AND NOT off_market AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
