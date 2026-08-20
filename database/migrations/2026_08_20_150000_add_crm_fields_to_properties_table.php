<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Campos do registo de imóvel equivalentes ao antigo CRM
|--------------------------------------------------------------------------
|
| O backoffice passa a ser o único sítio onde a carteira é registada, por isso
| precisa de guardar também o que o CRM guardava e que o site nunca mostra
| (contrato, chaves, finanças, licenças, comissão…).
|
| Critério, o mesmo do resto do schema: coluna própria para o que é mostrado no
| site, filtrado, ordenado ou pesquisado com frequência; jsonb para o resto.
|  - "admin"     → separador Interna (contrato, chaves, certificado energético
|                  completo, finanças, conservatória, licenças, comissão,
|                  encargos, importação/exportação) e a placa.
|  - "documents" → separador Media › Documentos (ficheiros anexos, nunca públicos).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Geral
            $table->string('internal_name', 191)->nullable()->after('reference');
            $table->string('typology', 16)->nullable()->after('property_condition');   // T0…T5+ (o CRM tem tipologia E n.º de quartos)
            $table->string('building_name', 191)->nullable()->after('floor_number');   // Prédio / Empreendimento
            $table->boolean('price_visible')->default(true)->after('currency');
            $table->boolean('is_sold')->default(false)->after('is_active');
            $table->boolean('off_market')->default(false)->after('is_sold');           // Propriedade fora de mercado
            $table->string('status_reason', 191)->nullable()->after('off_market');     // Motivo (quando inativa)

            // Localização
            $table->string('address', 255)->nullable()->after('zipcode');
            $table->string('street_number', 32)->nullable()->after('address');

            // Dados internos e anexos
            $table->jsonb('admin')->default('{}')->after('broker');
            $table->jsonb('documents')->default('[]')->after('admin');
        });

        // Vendidos e fora de mercado saem das listagens públicas: índice para o filtro.
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['is_active', 'is_sold', 'off_market'], 'properties_publishable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_publishable_idx');
            $table->dropColumn([
                'internal_name', 'typology', 'building_name', 'price_visible',
                'is_sold', 'off_market', 'status_reason',
                'address', 'street_number', 'admin', 'documents',
            ]);
        });
    }
};
