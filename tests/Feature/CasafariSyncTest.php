<?php

/*
 * Fase 3 — motor de sincronização com o feed do CASAFARI.
 * Usa a fixture tests/Fixtures/casafari-feed.xml (estrutura provisória, dados fictícios).
 */

use App\Enums\BusinessType;
use App\Events\PropertiesSynced;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const FIXTURE = __DIR__.'/../Fixtures/casafari-feed.xml';

/** Escreve uma variante da fixture num ficheiro temporário e devolve o caminho. */
function feedFile(?callable $mutate = null): string
{
    $xml = file_get_contents(FIXTURE);
    if ($mutate) {
        $xml = $mutate($xml);
    }
    $path = sys_get_temp_dir().'/casafari-test-'.uniqid().'.xml';
    file_put_contents($path, $xml);

    return $path;
}

/** Remove um <Property> inteiro pelo ID. */
function withoutProperty(string $xml, string $id): string
{
    return preg_replace('~<Property>\s*<ID>'.preg_quote($id, '~').'</ID>.*?</Property>~s', '', $xml, 1);
}

it('cria os imóveis do feed com os campos mapeados', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    expect(Property::count())->toBe(3);

    $p = Property::where('internal_id', '1001')->firstOrFail();

    expect($p->reference)->toBe('MF-1001')
        ->and((float) $p->price)->toBe(785000.0)
        ->and($p->business_type)->toBe(BusinessType::Sale)
        ->and($p->property_type)->toBe('Apartamento')
        ->and($p->bedrooms)->toBe(3)
        ->and((float) $p->gross_area)->toBe(160.5)
        ->and($p->city)->toBe('Cascais')
        ->and($p->locality)->toBe('Cascais e Estoril')
        ->and($p->gmap_visible)->toBeTrue()
        ->and($p->build_year)->toBe(2019)
        ->and($p->energy_rating)->toBe('B')
        ->and($p->virtual_tour_url)->toBe('https://tour.example.test/1001')
        ->and($p->is_exclusive)->toBeTrue()
        ->and($p->is_featured)->toBeFalse()
        ->and($p->is_active)->toBeTrue()
        ->and($p->crm_updated_at?->equalTo(Carbon::parse('2026-08-10T09:30:00+01:00')))->toBeTrue()
        ->and($p->slug)->toBe('apartamento-cascais-mf-1001')
        ->and(strlen($p->payload_hash))->toBe(64);

    // Traduções por idioma (atributo lang), CDATA e entidades tratadas.
    expect($p->translations['pt']['title'])->toBe('Apartamento T3 com terraço e vista de mar')
        ->and($p->translations['en']['title'])->toBe('3-bedroom apartment with terrace and sea view')
        ->and($p->translations['pt']['description'])->toContain('três frentes & terraço');

    // Fotos ordenadas por "order", URL inválido descartado.
    expect($p->photos)->toHaveCount(2)
        ->and($p->photos[0]['url'])->toBe('https://cdn.example.test/1001/a.jpg')
        ->and($p->photos[1]['url'])->toBe('https://cdn.example.test/1001/b.jpg');

    // Características normalizadas e sem duplicados.
    expect($p->features)->toBe(['elevador', 'garagem']);

    // Broker: só nome e foto.
    expect($p->broker)->toBe(['name' => 'Ana Consultora', 'photo' => 'https://cdn.example.test/brokers/ana.jpg']);
});

it('normaliza a finalidade em português e trata gmap_visible=0', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    $terreno = Property::where('internal_id', '1003')->firstOrFail();
    $t2 = Property::where('internal_id', '1002')->firstOrFail();

    expect($terreno->business_type)->toBe(BusinessType::Sale)   // "Venda"
        ->and($terreno->currency)->toBe('EUR')                    // default
        ->and($t2->business_type)->toBe(BusinessType::Rent)
        ->and($t2->gmap_visible)->toBeFalse()
        ->and($t2->coordinates)->toBeNull()
        ->and($t2->lat)->not->toBeNull();
});

it('NUNCA persiste os dados do proprietário (Owner)', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    // Procura em TODAS as colunas de TODAS as linhas — não só nas que "deviam" ter o Owner.
    $rows = DB::table('properties')->get();
    $dump = strtolower(json_encode($rows, JSON_UNESCAPED_UNICODE));

    expect($dump)->not->toContain('proprietario@example.test')
        ->and($dump)->not->toContain('proprietário fictício')
        ->and($dump)->not->toContain('outro@example.test')
        ->and($dump)->not->toContain('900 000 999')
        // Contactos do consultor também não.
        ->and($dump)->not->toContain('ana@example.test')
        ->and($dump)->not->toContain('900 000 001');
});

it('salta a escrita quando o hash não mudou', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    $before = Property::where('internal_id', '1001')->firstOrFail();
    $updatedAt = $before->updated_at;
    $this->travel(5)->minutes();

    $this->artisan('casafari:sync', ['--file' => feedFile()])
        ->expectsOutputToContain('Inalterados (hash igual)')
        ->assertSuccessful();

    $after = Property::where('internal_id', '1001')->firstOrFail();

    // updated_at não mexeu (nenhuma escrita de atributos); synced_at avançou.
    expect($after->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($after->synced_at->greaterThan($before->synced_at))->toBeTrue()
        ->and(Property::count())->toBe(3);
});

it('atualiza quando o conteúdo muda e mantém o slug', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();
    $slug = Property::where('internal_id', '1001')->value('slug');

    $changed = feedFile(fn ($xml) => str_replace(
        ['<Title lang="pt">Apartamento T3 com terraço e vista de mar</Title>', '<Price>785000</Price>', '<City>Cascais</City>'],
        ['<Title lang="pt">Título completamente novo</Title>', '<Price>750000</Price>', '<City>Oeiras</City>'],
        $xml
    ));

    $this->artisan('casafari:sync', ['--file' => $changed])->assertSuccessful();

    $p = Property::where('internal_id', '1001')->firstOrFail();

    expect($p->title)->toBe('Título completamente novo')
        ->and((float) $p->price)->toBe(750000.0)
        ->and($p->city)->toBe('Oeiras')
        ->and($p->slug)->toBe($slug)          // slug estável, mesmo com título E concelho diferentes
        ->and(Property::count())->toBe(3);
});

it('desativa (sem apagar) o que desapareceu do feed', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    $this->artisan('casafari:sync', ['--file' => feedFile(fn ($xml) => withoutProperty($xml, '1002'))])
        ->assertSuccessful();

    expect(Property::count())->toBe(3)
        ->and(Property::where('internal_id', '1002')->value('is_active'))->toBeFalse()
        ->and(Property::active()->count())->toBe(2);

    // Volta a aparecer → reativa.
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();
    expect(Property::where('internal_id', '1002')->value('is_active'))->toBeTrue();
});

it('aborta com FAILURE se o feed vier vazio, sem desativar nada', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    $empty = feedFile(fn () => '<?xml version="1.0"?><Feed><Properties></Properties></Feed>');

    $this->artisan('casafari:sync', ['--file' => $empty])
        ->expectsOutputToContain('devolveu 0 imóveis')
        ->assertFailed();

    expect(Property::active()->count())->toBe(3);
});

it('respeita o mínimo de imóveis configurado (feed truncado)', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    config(['casafari.min_items' => 3]);
    $truncated = feedFile(fn ($xml) => withoutProperty(withoutProperty($xml, '1002'), '1003'));

    $this->artisan('casafari:sync', ['--file' => $truncated])->assertFailed();

    expect(Property::active()->count())->toBe(3);
});

it('--dry-run não escreve nada', function () {
    Event::fake();

    $this->artisan('casafari:sync', ['--file' => feedFile(), '--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect(Property::count())->toBe(0);
    Event::assertNotDispatched(PropertiesSynced::class);
});

it('--force reescreve mesmo com hash igual', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();
    $updatedAt = Property::where('internal_id', '1001')->value('updated_at');
    $this->travel(5)->minutes();

    $this->artisan('casafari:sync', ['--file' => feedFile(), '--force' => true])->assertSuccessful();

    expect(Property::where('internal_id', '1001')->firstOrFail()->updated_at->greaterThan($updatedAt))->toBeTrue();
});

it('dispara PropertiesSynced no fim de um sync real', function () {
    Event::fake();

    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    Event::assertDispatched(PropertiesSynced::class, fn ($e) => $e->result->created === 3);
});

it('não desativa nada quando houve erros de mapeamento', function () {
    $this->artisan('casafari:sync', ['--file' => feedFile()])->assertSuccessful();

    // Um imóvel sem ID e sem o 1002: o erro deve impedir a desativação do 1002.
    $broken = feedFile(fn ($xml) => str_replace('<ID>1003</ID>', '<ID></ID>', withoutProperty($xml, '1002')));

    $this->artisan('casafari:sync', ['--file' => $broken])
        ->expectsOutputToContain('saltado: houve erros')
        ->assertFailed();

    expect(Property::active()->count())->toBe(3);
});

it('descarrega o feed com retries e guarda latest.xml', function () {
    Storage::fake('local');
    config(['casafari.feed_url' => 'https://feed.example.test/casafari.xml']);

    Http::fake([
        'feed.example.test/*' => Http::response(file_get_contents(FIXTURE), 200, ['Content-Type' => 'application/xml']),
    ]);

    $this->artisan('casafari:sync')->assertSuccessful();

    Http::assertSentCount(1);
    Storage::disk('local')->assertExists('casafari/latest.xml');
    expect(Property::count())->toBe(3);
});

it('falha sem tocar na base de dados quando o download falha', function () {
    Storage::fake('local');
    config(['casafari.feed_url' => 'https://feed.example.test/casafari.xml', 'casafari.feed_retries' => 1, 'casafari.feed_retry_delay_ms' => 0]);
    Property::factory()->create();

    Http::fake(['feed.example.test/*' => Http::response('erro', 500)]);

    $this->artisan('casafari:sync')->assertFailed();

    expect(Property::active()->count())->toBe(1);
});

it('não corre sem CASAFARI_FEED_URL', function () {
    config(['casafari.feed_url' => null]);

    $this->artisan('casafari:sync')
        ->expectsOutputToContain('CASAFARI_FEED_URL')
        ->assertFailed();
});
