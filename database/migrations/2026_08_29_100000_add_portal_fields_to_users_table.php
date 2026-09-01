<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| users — o que o portal precisa
|--------------------------------------------------------------------------
|
| is_active: uma conta desativada não entra e, se já tiver sessão, é posta
| fora no pedido seguinte (EnsureAccountActive). Melhor do que apagar: fica
| o histórico de quem fez o quê.
|
| last_login_at: a última entrada, para a lista da equipa.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->timestampTz('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_login_at']);
        });
    }
};
