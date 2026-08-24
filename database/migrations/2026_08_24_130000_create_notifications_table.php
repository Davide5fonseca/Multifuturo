<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Notificações no backoffice
|--------------------------------------------------------------------------
|
| Tabela padrão do Laravel para notificações em base de dados: alimenta o
| sino do painel (Filament). Hoje é usada para avisar a equipa de cada novo
| pedido chegado pelo site, além do email que já ia para a agência.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data'); // jsonb: o Filament filtra por data->>'format'
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
