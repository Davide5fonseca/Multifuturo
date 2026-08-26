<?php

/*
 * Casos-limite transversais: escape de conteúdo nas views, leads em JSON,
 * listagem e zonas.
 */

use App\Http\Requests\StoreLeadRequest;
use App\Livewire\PropertyListing;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Conteúdo é escapado nas views (XSS)
|--------------------------------------------------------------------------
*/

it('escapa HTML em título, descrição, características e broker', function () {
    $p = Property::factory()->create([
        'translations' => ['pt' => ['title' => 'Casa <script>alert(1)</script>', 'description' => 'Linda <img src=x onerror=alert(2)>']],
        'features' => ['<b>garagem</b>'],
        'broker' => ['name' => '<i>Ana</i>', 'photo' => null],
        'city' => 'Cascais',
    ]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('<img src=x onerror')
        ->and($html)->not->toContain('<b>garagem</b>')
        ->and($html)->not->toContain('<i>Ana</i>');

    // E no cartão da listagem também.
    $list = $this->get(route('buy'))->getContent();
    expect($list)->not->toContain('<script>alert(1)</script>');
});

it('o JSON-LD escapa "</script>" para não fechar o bloco', function () {
    $p = Property::factory()->create(['translations' => ['pt' => ['title' => 'Fecho </script><script>alert(3)</script>', 'description' => 'x']]]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('</script><script>alert(3)')
        ->and($html)->toContain('</script>');
});

/*
|--------------------------------------------------------------------------
| Leads — resposta JSON e limites
|--------------------------------------------------------------------------
*/

it('aceita pedidos JSON e responde 201 com mensagem', function () {
    Queue::fake();

    $this->postJson(route('leads.store'), [
        'source' => 'contact', 'name' => 'Ana Silva', 'email' => 'ana@example.test',
        'form_ts' => StoreLeadRequest::signedTimestamp(time() - 20),
    ])->assertCreated()->assertJson(['ok' => true]);

    expect(Lead::count())->toBe(1);
});

it('rejeita mensagens demasiado longas e nomes de 1 carácter em JSON com 422', function () {
    Queue::fake();

    $this->postJson(route('leads.store'), ['source' => 'contact', 'name' => 'A', 'email' => 'a@example.test', 'message' => str_repeat('x', 3001)])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'message']);
});

/*
|--------------------------------------------------------------------------
| Listagem — mais filtros
|--------------------------------------------------------------------------
*/

it('filtra por tipo, área e freguesia, e limpa a freguesia ao mudar de concelho', function () {
    $a = Property::factory()->create(['property_type' => 'Moradia', 'house_area' => 200, 'gross_area' => 220, 'city' => 'Cascais', 'locality' => 'Estoril']);
    $b = Property::factory()->create(['property_type' => 'Apartamento', 'house_area' => 80, 'gross_area' => 90, 'city' => 'Cascais', 'locality' => 'Carcavelos']);

    $c = Livewire::test(PropertyListing::class, ['businessType' => 'sale'])
        ->set('type', 'moradia')->assertSee($a->slug)->assertDontSee($b->slug)
        ->set('type', '')
        ->set('areaMin', '100')->assertSee($a->slug)->assertDontSee($b->slug)
        ->set('areaMin', '')
        ->set('city', 'Cascais')->set('locality', 'Carcavelos')->assertSee($b->slug)->assertDontSee($a->slug)
        ->set('city', 'Lisboa');

    expect($c->get('locality'))->toBe('');
});

it('limita as características vindas do URL a 12 e normaliza-as', function () {
    $c = Livewire::withQueryParams(['caracteristicas' => array_merge(array_fill(0, 20, ' GARAGEM '), ['<x>'])])
        ->test(PropertyListing::class, ['businessType' => 'sale']);

    $features = $c->get('features');
    expect(count($features))->toBeLessThanOrEqual(12)->and($features)->toContain('garagem');
});

/*
|--------------------------------------------------------------------------
| Zonas — slugs com acentos e freguesia errada
|--------------------------------------------------------------------------
*/

it('as zonas com acentos têm slugs ASCII e a freguesia errada dá 404', function () {
    Property::factory()->create(['city' => 'Setúbal', 'locality' => 'São Sebastião']);

    $this->get(route('zones.city', 'setubal'))->assertOk()->assertSee('Setúbal');
    $this->get(route('zones.locality', ['setubal', 'sao-sebastiao']))->assertOk();
    $this->get(route('zones.locality', ['setubal', 'nao-existe']))->assertNotFound();
});
