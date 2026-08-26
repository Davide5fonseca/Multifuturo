<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Fim do código do CRM
|--------------------------------------------------------------------------
|
| O site nunca chegou a ligar-se ao CRM da CASAFARI (decisão de 2026-08-19):
| a carteira é gerida no backoffice. O motor de sincronização, o envio de
| leads e as colunas que os serviam ficaram sem uso — saem de vez.
|
| Nas leads: crm_status, crm_response, sent_at, attempts e last_error só
| existiam para o envio ao CRM. Nos imóveis: synced_at só existia para o sync.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_crm_status_check');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['crm_status', 'created_at']);
            $table->dropColumn(['crm_status', 'crm_response', 'sent_at', 'attempts', 'last_error']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('crm_status', 16)->default('pending');
            $table->jsonb('crm_response')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->index(['crm_status', 'created_at']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->timestampTz('synced_at')->nullable();
        });
    }
};
