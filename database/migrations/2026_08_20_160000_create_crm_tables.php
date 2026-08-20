<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Módulos de CRM do backoffice
|--------------------------------------------------------------------------
|
| O backoffice deixa de ser só "gestão de imóveis" e passa a fazer o que a
| equipa fazia no CRM: clientes, leads com pipeline, agenda, histórico de
| alterações e estatísticas de interesse.
|
|  contacts            clientes (compradores, proprietários, ambos)
|  events              agenda: telefonemas, visitas, reuniões, lembretes
|  property_activities histórico automático (nova, preço alterado, desativada…)
|  property_views      cliques/visualizações das fichas, para o gráfico
|
| As leads ganham pipeline (tipo, estado, prioridade, responsável, cliente).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('email', 254)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('kind', 16)->default('buyer');       // buyer | owner | both
            $table->string('city', 96)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('preferences')->default('{}');        // procura: tipologia, zona, orçamento…
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('name');
            $table->index('email');
            $table->index(['kind', 'created_at']);
        });
        DB::statement("ALTER TABLE contacts ADD CONSTRAINT contacts_kind_check CHECK (kind IN ('buyer','owner','both'))");

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('type', 24)->default('call');        // call | visit | meeting | task | reminder
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_done')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['starts_at', 'is_done']);
            $table->index(['user_id', 'starts_at']);
        });
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_type_check CHECK (type IN ('call','visit','meeting','task','reminder'))");

        Schema::create('property_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);                          // created | price | status | updated | deleted
            $table->string('detail', 255)->nullable();           // "520 001 € → 520 000 €"
            $table->timestampsTz();

            $table->index(['created_at']);
            $table->index(['property_id', 'created_at']);
        });

        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('viewed_on');                           // agregado por dia: sem dados pessoais
            $table->unsignedInteger('views')->default(1);
            $table->timestampsTz();

            $table->unique(['property_id', 'viewed_on']);
            $table->index('viewed_on');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('kind', 16)->default('buyer')->after('source');      // buyer | listing (angariação)
            $table->string('status', 32)->default('received')->after('kind');   // pipeline
            $table->string('priority', 16)->default('normal')->after('status'); // normal | high | urgent
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('assigned_to')->constrained('contacts')->nullOnDelete();
            $table->text('internal_notes')->nullable()->after('crm_response');

            $table->index(['kind', 'status', 'created_at']);
        });
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_kind_check CHECK (kind IN ('buyer','listing'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_priority_check CHECK (priority IN ('normal','high','urgent'))");
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn(['kind', 'status', 'priority', 'internal_notes']);
        });

        Schema::dropIfExists('property_views');
        Schema::dropIfExists('property_activities');
        Schema::dropIfExists('events');
        Schema::dropIfExists('contacts');
    }
};
