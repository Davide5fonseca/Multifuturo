<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| property_alerts — "avise-me quando entrar um imóvel assim"
|--------------------------------------------------------------------------
|
| Um visitante deixa o email e os filtros da listagem; quando entra um imóvel
| que encaixe, recebe-o por email (alerts:send, de hora a hora). RGPD:
| confirmação por email antes de qualquer envio (double opt-in), ligação
| para cancelar em todos os emails, versão da política e IP em hash — o
| mesmo que nas leads. O email só serve para isto.
|
| criteria é jsonb com as chaves normalizadas (ver PropertyFilters), com as
| chaves ordenadas: assim dois pedidos iguais comparam-se com "=".
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254);
            $table->string('name', 120)->nullable();
            $table->string('locale', 5)->default('pt');
            $table->string('listing', 8);                       // buy | rent (a listagem de onde veio)
            $table->jsonb('criteria')->default('{}');
            $table->string('token', 64)->unique();              // liga confirmar / cancelar
            $table->timestampTz('confirmed_at')->nullable();    // double opt-in
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampTz('last_sent_at')->nullable();    // published_at do último imóvel enviado
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('policy_version', 32)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampsTz();

            $table->index(['email', 'listing']);
            $table->index(['confirmed_at', 'unsubscribed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_alerts');
    }
};
