<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| module_access — o módulo 'imoveis' passa a chamar-se 'backoffice'
|--------------------------------------------------------------------------
|
| O portal passa a ter dois cartões: Site (público, sem acesso explícito) e
| Backoffice (a gestão, antes "Imóveis"). Os acessos já dados mantêm-se;
| só a chave muda.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_access')->where('module', 'imoveis')->update(['module' => 'backoffice']);
    }

    public function down(): void
    {
        DB::table('module_access')->where('module', 'backoffice')->update(['module' => 'imoveis']);
    }
};
