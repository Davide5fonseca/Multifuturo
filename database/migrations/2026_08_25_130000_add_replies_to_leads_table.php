<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Respostas aos clientes
|--------------------------------------------------------------------------
|
| Cada resposta enviada a partir do backoffice fica registada na própria
| dúvida: quem respondeu, quando e o quê. Sem isto, duas pessoas da equipa
| podiam responder à mesma pergunta sem saber uma da outra — e ninguém
| saberia, semanas depois, o que foi dito ao cliente.
|
| jsonb e não tabela à parte: são poucas respostas por dúvida, sempre lidas
| em conjunto com ela e nunca pesquisadas isoladamente.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->jsonb('replies')->default('[]')->after('internal_notes');
            $table->timestamp('replied_at')->nullable()->after('replies');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['replies', 'replied_at']);
        });
    }
};
