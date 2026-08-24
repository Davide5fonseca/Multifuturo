<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Agenda com todos os tipos de evento do CRM
|--------------------------------------------------------------------------
|
| A agenda tinha cinco tipos; o CRM tem doze (email, escritura, CPCV, oferta,
| chegadas, dia de serviço, outros). A restrição CHECK passa a aceitá-los
| todos — os valores vivem em App\Enums\EventType.
|
*/
return new class extends Migration
{
    private const TYPES = [
        'call', 'visit', 'email', 'deed', 'meeting', 'task',
        'reminder', 'other', 'arrival', 'cpcv', 'service_day', 'offer',
    ];

    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_type_check');
        DB::statement(sprintf(
            "ALTER TABLE events ADD CONSTRAINT events_type_check CHECK (type IN ('%s'))",
            implode("','", self::TYPES),
        ));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_type_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_type_check CHECK (type IN ('call','visit','meeting','task','reminder'))");
    }
};
