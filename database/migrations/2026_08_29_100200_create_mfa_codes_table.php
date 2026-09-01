<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| mfa_codes — códigos da verificação em duas etapas
|--------------------------------------------------------------------------
|
| Uso único, validade curta, só o hash fica guardado (nunca o código em
| claro). Uma pessoa só tem um código vivo de cada vez.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestampTz('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_codes');
    }
};
