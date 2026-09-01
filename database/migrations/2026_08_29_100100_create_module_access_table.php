<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| module_access — quem entra em que módulo
|--------------------------------------------------------------------------
|
| Uma linha por pessoa e por módulo (config/modules.php). Os administradores
| não precisam de linhas: veem tudo. O "role" é texto livre cujo significado
| pertence a cada módulo — o portal só o guarda.
|
| Quem já tinha conta recebe acesso ao módulo de imóveis: era o único que
| existia, e ninguém pode ficar de fora por causa desta migração.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('role', 40)->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'module']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO module_access (user_id, module, created_at, updated_at)
            SELECT id, 'imoveis', NOW(), NOW() FROM users
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('module_access');
    }
};
