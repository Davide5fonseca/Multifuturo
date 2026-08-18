<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| properties — réplica local da carteira do CASAFARI CRM
|--------------------------------------------------------------------------
|
| Colunas próprias só para o que é filtrado, ordenado ou mostrado em lista;
| o resto vive em jsonb (translations, photos, features, broker).
|
| Regra de privacidade: o feed traz um elemento Owner (proprietário) com nome,
| email e telefone. NÃO existe coluna para isso e o mapper ignora-o de forma
| explícita. Do Broker guardam-se apenas nome e foto.
|
| Nunca se apagam linhas: o feed é um snapshot completo, e o que desaparece
| passa a is_active = false (a ficha continua a responder 410/redirect).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // Identidade no CRM. internal_id é a chave do upsert; reference é o código público (ex.: MF-2041).
            $table->string('internal_id', 64)->unique();
            $table->string('reference', 64)->nullable()->index();

            // Negócio e tipo.
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('business_type', 16);          // sale | rent (App\Enums\BusinessType)
            $table->string('property_type', 64)->nullable()->index();
            $table->string('property_condition', 64)->nullable();

            // Divisões e áreas (m²).
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->decimal('house_area', 10, 2)->nullable();
            $table->decimal('plot_area', 10, 2)->nullable();
            $table->decimal('gross_area', 10, 2)->nullable();

            // Localização. Distrito/concelho/freguesia + zona textual do CRM.
            $table->char('country', 2)->default('PT');
            $table->string('district', 96)->nullable()->index();
            $table->string('city', 96)->nullable();       // concelho
            $table->string('locality', 96)->nullable();   // freguesia
            $table->string('zone', 96)->nullable();
            $table->string('zipcode', 16)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            // Compromisso contratual com o proprietário: se false, as coordenadas nunca saem do servidor.
            $table->boolean('gmap_visible')->default(false);

            // Edifício.
            $table->smallInteger('floor_number')->nullable();
            $table->unsignedSmallInteger('build_year')->nullable();
            $table->string('energy_rating', 8)->nullable();

            // Ligações externas.
            $table->string('crm_property_url', 2048)->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->string('virtual_tour_url', 2048)->nullable();
            $table->string('floorplan_url', 2048)->nullable();

            // Conteúdo semiestruturado.
            $table->jsonb('translations')->default('{}');  // { "pt": { "title": …, "description": … } }
            $table->jsonb('photos')->default('[]');        // [ { "url": …, "order": n }, … ]
            $table->jsonb('features')->default('[]');      // [ "elevador", "garagem", … ] — GIN abaixo
            $table->jsonb('broker')->nullable();           // { "name": …, "photo": … } — sem contactos

            // Publicação e sincronização.
            $table->string('slug', 191)->unique();         // estável: nunca muda depois de gerado
            $table->char('payload_hash', 64);              // sha256 do nó XML bruto — salta escritas iguais
            $table->timestampTz('crm_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_exclusive')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestampTz('synced_at')->nullable();

            $table->timestampsTz();

            // Listagens: filtro por finalidade e ordenação por preço só sobre imóveis ativos.
            $table->index(['is_active', 'business_type', 'price'], 'properties_active_business_price_idx');
            // Pesquisa por localização e páginas de zona.
            $table->index(['city', 'locality'], 'properties_city_locality_idx');
            // Ordenação por data de entrada/atualização no CRM.
            $table->index(['is_active', 'crm_updated_at'], 'properties_active_crm_updated_idx');
        });

        // Índice GIN para filtrar por características (features @> '["garagem"]').
        DB::statement('CREATE INDEX properties_features_gin_idx ON properties USING GIN (features jsonb_path_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
