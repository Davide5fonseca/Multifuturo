<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| leads — contactos captados no site, a enviar ao CASAFARI via queue
|--------------------------------------------------------------------------
|
| A lead grava-se localmente PRIMEIRO; o envio ao CRM é um job com retries.
| Se o CRM estiver em baixo, o contacto não se perde: fica pending/failed
| aqui, com a resposta do CRM em crm_response para diagnóstico.
|
| Privacidade: guardamos o hash do IP (não o IP), os dois consentimentos em
| separado e a versão da política de privacidade em vigor no momento.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Contacto.
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('phone', 32)->nullable();
            $table->text('message')->nullable();

            // Contexto.
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('business_type', 16)->nullable();     // sale | rent (se aplicável)
            $table->string('source', 32);                         // property | contact | valuation (App\Enums\LeadSource)
            $table->jsonb('payload')->nullable();                 // campos extra do formulário (ex.: avaliação: morada, área…)

            // RGPD.
            $table->boolean('consent_contact')->default(false);   // IncludeOptIn — ser contactado sobre este pedido
            $table->boolean('consent_marketing')->default(false); // IncludeMailing — newsletter/comunicações
            $table->string('policy_version', 32);                 // versão da política aceite/apresentada
            $table->char('ip_hash', 64)->nullable();              // sha256(ip + APP_KEY), nunca o IP em claro
            $table->string('user_agent', 255)->nullable();

            // Estado no CRM.
            $table->string('crm_status', 16)->default('pending'); // pending | sent | failed (App\Enums\LeadStatus)
            $table->jsonb('crm_response')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestampsTz();

            $table->index(['crm_status', 'created_at']);
            $table->index('email');
        });

        // Restrição de domínio dos estados — protege contra escrita de valores fora do enum.
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_crm_status_check CHECK (crm_status IN ('pending', 'sent', 'failed'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source IN ('property', 'contact', 'valuation'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
