<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| consent_logs — prova das escolhas de cookies
|--------------------------------------------------------------------------
|
| O aviso de cookies guarda as escolhas no browser da pessoa (cookie
| mf_consent). Isso chega para as respeitar; não chega para as provar. O
| RGPD pede que o responsável consiga demonstrar que houve consentimento
| (art. 7.º, n.º 1): cada escolha — aceitar, recusar, personalizar — fica
| aqui registada com a data, a versão do aviso, as categorias e um
| identificador técnico derivado do IP (hash, não o IP). Sem nome, sem
| email, sem cookie de sessão: não identifica ninguém por si só.
|
| Apagam-se ao fim de 24 meses (ConsentLog::prunable, model:prune diário).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('version');          // versão do aviso (config consent.version)
            $table->jsonb('choices');                          // {"analytics": false, "marketing": false}
            $table->string('action', 16);                      // accept_all | reject_all | custom
            $table->string('locale', 5)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
