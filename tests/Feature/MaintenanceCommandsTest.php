<?php

/*
 * Comandos de manutenção: leads:retry e zones:import.
 */

use App\Enums\LeadStatus;
use App\Jobs\SendLeadToCasafari;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Zone;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| leads:retry
|--------------------------------------------------------------------------
*/

it('reenvia as leads failed e volta a marcá-las pending', function () {
    Queue::fake();
    $failed = Lead::factory()->create(['crm_status' => LeadStatus::Failed, 'last_error' => 'CRM em baixo', 'attempts' => 5]);
    $sent = Lead::factory()->create(['crm_status' => LeadStatus::Sent]);
    $freshPending = Lead::factory()->create(['crm_status' => LeadStatus::Pending]);

    $this->artisan('leads:retry')->assertSuccessful();

    Queue::assertPushed(SendLeadToCasafari::class, 1);
    Queue::assertPushed(SendLeadToCasafari::class, fn ($job) => $job->leadId === $failed->id);

    expect($failed->fresh()->crm_status)->toBe(LeadStatus::Pending)
        ->and($failed->fresh()->last_error)->toBeNull()
        ->and($sent->fresh()->crm_status)->toBe(LeadStatus::Sent)
        ->and($freshPending->fresh()->crm_status)->toBe(LeadStatus::Pending);
});

it('--pending inclui as pending paradas há mais de 1 hora, mas não as recentes', function () {
    Queue::fake();
    $stale = Lead::factory()->create(['crm_status' => LeadStatus::Pending, 'created_at' => now()->subHours(3)]);
    $fresh = Lead::factory()->create(['crm_status' => LeadStatus::Pending, 'created_at' => now()->subMinutes(5)]);

    $this->artisan('leads:retry', ['--pending' => true])->assertSuccessful();

    Queue::assertPushed(SendLeadToCasafari::class, fn ($job) => $job->leadId === $stale->id);
    Queue::assertNotPushed(SendLeadToCasafari::class, fn ($job) => $job->leadId === $fresh->id);
});

it('--id reenvia leads específicas e --dry-run não despacha nada', function () {
    Queue::fake();
    $a = Lead::factory()->create(['crm_status' => LeadStatus::Failed]);
    $b = Lead::factory()->create(['crm_status' => LeadStatus::Failed]);

    $this->artisan('leads:retry', ['--id' => [$a->id]])->assertSuccessful();
    Queue::assertPushed(SendLeadToCasafari::class, 1);

    $this->artisan('leads:retry', ['--dry-run' => true])->assertSuccessful();
    Queue::assertPushed(SendLeadToCasafari::class, 1); // continua 1: dry-run não somou
    expect($b->fresh()->crm_status)->toBe(LeadStatus::Failed);
});

it('sem nada para reenviar informa e sai com sucesso', function () {
    $this->artisan('leads:retry')->expectsOutputToContain('Nada para reenviar')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| zones:import
|--------------------------------------------------------------------------
*/

function zonesDir(array $files): string
{
    $dir = sys_get_temp_dir().'/zones-'.uniqid();
    File::makeDirectory($dir);
    foreach ($files as $name => $content) {
        File::put("{$dir}/{$name}", $content);
    }

    return $dir;
}

it('importa concelho e freguesia com front matter, intro e body', function () {
    $dir = zonesDir([
        'cascais.md' => "---\ncity_slug: cascais\ntitle: Viver em Cascais\nmeta_description: Casas em Cascais.\n---\nIntro da zona.\n\nPrimeiro parágrafo do corpo.\n\nSegundo parágrafo.",
        'cascais--estoril.md' => "---\ncity_slug: Cascais\nlocality_slug: Estoril\n---\nSó intro, sem corpo.",
    ]);

    $this->artisan('zones:import', ['--path' => $dir])->assertSuccessful();

    $city = Zone::where('city_slug', 'cascais')->whereNull('locality_slug')->firstOrFail();
    expect($city->title)->toBe('Viver em Cascais')
        ->and($city->intro)->toBe('Intro da zona.')
        ->and($city->body)->toBe("Primeiro parágrafo do corpo.\n\nSegundo parágrafo.")
        ->and($city->is_published)->toBeTrue();

    $locality = Zone::where('city_slug', 'cascais')->where('locality_slug', 'estoril')->firstOrFail();
    expect($locality->intro)->toBe('Só intro, sem corpo.')->and($locality->body)->toBeNull();
});

it('reimportar atualiza em vez de duplicar, e published: false despublica', function () {
    $dir = zonesDir(['c.md' => "---\ncity_slug: cascais\ntitle: V1\n---\nIntro."]);
    $this->artisan('zones:import', ['--path' => $dir]);

    File::put("{$dir}/c.md", "---\ncity_slug: cascais\ntitle: V2\npublished: false\n---\nIntro nova.");
    $this->artisan('zones:import', ['--path' => $dir])->assertSuccessful();

    expect(Zone::count())->toBe(1)
        ->and(Zone::first()->title)->toBe('V2')
        ->and(Zone::first()->is_published)->toBeFalse();
});

it('--prune despublica zonas sem ficheiro e um ficheiro sem city_slug conta como erro', function () {
    Zone::create(['city_slug' => 'orfa', 'title' => 'Sem ficheiro', 'is_published' => true]);
    $dir = zonesDir([
        'ok.md' => "---\ncity_slug: cascais\n---\nIntro.",
        'mau.md' => "---\ntitle: Sem slug\n---\nIntro.",
    ]);

    $this->artisan('zones:import', ['--path' => $dir, '--prune' => true])->assertFailed(); // erro no mau.md

    expect(Zone::where('city_slug', 'orfa')->first()->is_published)->toBeFalse()
        ->and(Zone::where('city_slug', 'cascais')->exists())->toBeTrue();
});

it('a página de zona mostra o conteúdo importado', function () {
    Property::factory()->create(['city' => 'Cascais']);
    $dir = zonesDir(['c.md' => "---\ncity_slug: cascais\ntitle: Viver em Cascais\n---\nEntre a serra e o mar.\n\nCorpo editorial."]);
    $this->artisan('zones:import', ['--path' => $dir]);

    $this->get(route('zones.city', 'cascais'))->assertOk()
        ->assertSee('Viver em Cascais')
        ->assertSee('Entre a serra e o mar.')
        ->assertSee('Corpo editorial.');
});
