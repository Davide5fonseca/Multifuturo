<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Administradores da equipa
|--------------------------------------------------------------------------
|
| Até aqui qualquer conta podia tudo, e não havia forma de criar contas sem
| ser por linha de comandos. Passa a haver uma distinção mínima: os
| administradores gerem as contas da equipa, os restantes usam o backoffice
| normalmente.
|
| Não é um sistema de permissões — é o mínimo para a equipa poder crescer sem
| ninguém ter de tocar no servidor. Se um dia forem precisos mais níveis
| (consultor só vê os imóveis dele, por exemplo), constrói-se sobre isto.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email_verified_at');
        });

        // As contas que já existem são de quem montou o sistema: ficam
        // administradoras, senão ninguém conseguiria criar a primeira.
        DB::table('users')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
