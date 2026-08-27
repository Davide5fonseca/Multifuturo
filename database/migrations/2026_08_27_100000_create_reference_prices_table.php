<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| reference_prices — €/m² por concelho e tipo, para a estimativa imediata
|--------------------------------------------------------------------------
|
| Alimenta o simulador "Quanto vale a minha casa?". A agência escreve aqui o
| valor por m² que acompanha (INE, portais, as suas próprias vendas). Sem
| linha para um concelho, o simulador cai para a mediana das nossas vendas
| publicadas lá (ver App\Support\Valuation); sem nenhuma das duas, não
| estima e convida ao pedido de avaliação.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_prices', function (Blueprint $table) {
            $table->id();
            $table->string('city', 96);                 // nome do concelho, como nas fichas
            $table->string('property_type', 32);        // apartment | house | land
            $table->decimal('price_per_m2', 10, 2);
            $table->text('notes')->nullable();          // fonte e data do valor
            $table->timestampsTz();

            $table->unique(['city', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_prices');
    }
};
