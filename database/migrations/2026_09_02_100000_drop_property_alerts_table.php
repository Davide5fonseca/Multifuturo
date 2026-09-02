<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Os alertas de imóveis ("avise-me quando entrar um imóvel assim") foram
 * retirados a pedido do cliente — formulário, envios, emails e backoffice.
 * A tabela vai com eles; o down() volta a criá-la com a estrutura que tinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('property_alerts');
    }

    public function down(): void
    {
        Schema::create('property_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254);
            $table->string('name', 120)->nullable();
            $table->string('locale', 5)->default('pt');
            $table->string('listing', 8);
            $table->jsonb('criteria')->default('{}');
            $table->string('token', 64)->unique();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampTz('last_sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('policy_version', 32)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampsTz();

            $table->index(['email', 'listing']);
            $table->index(['confirmed_at', 'unsubscribed_at']);
        });
    }
};
