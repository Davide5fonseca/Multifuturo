<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Detalhes estruturados do imóvel (separador Detalhes › Geral)
|--------------------------------------------------------------------------
|
| Os campos com valor (pisos, orientação, ocupação, ano de renovação,
| orientação solar) vivem aqui; as comodidades de sim/não continuam no array
| `features`, que alimenta os filtros do site pelo índice GIN.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->jsonb('details')->default('{}')->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
