<?php

/*
 * valuation:import-ine — enche os valores de referência a partir da API do
 * INE (avaliação bancária por tipo + vendas por concelho e freguesia), sem
 * pisar o que a agência escreveu à mão.
 */

use App\Models\ReferencePrice;
use App\Support\PropertyCache;
use App\Support\Valuation;
use Illuminate\Support\Facades\Http;

/** Resposta do INE, no formato real (lista com um objeto; Dados → período → linhas). */
function respostaIne(string $periodo, array $linhas): array
{
    return [['IndicadorCod' => 'x', 'UltimoPref' => $periodo, 'Dados' => [$periodo => $linhas]]];
}

function fakeIne(): void
{
    Http::fake(function ($request) {
        $varcd = $request->data()['varcd'] ?? null;

        if ($varcd === config('valuation.ine.appraisal')) {
            return Http::response(respostaIne('Julho de 2026', [
                ['geocod' => '1A01111', 'geodsg' => 'Sintra', 'dim_3' => '1', 'dim_3_t' => 'Apartamentos', 'valor' => '2969'],
                ['geocod' => '1A01111', 'geodsg' => 'Sintra', 'dim_3' => '2', 'dim_3_t' => 'Moradias', 'valor' => '2868'],
                ['geocod' => '1A01111', 'geodsg' => 'Sintra', 'dim_3' => 'T', 'dim_3_t' => 'Total', 'valor' => '2957'],
                ['geocod' => '11E0411', 'geodsg' => 'Vimioso', 'dim_3' => '1', 'dim_3_t' => 'Apartamentos', 'sinal_conv' => '…'],
                ['geocod' => '15', 'geodsg' => 'Algarve', 'dim_3' => '1', 'dim_3_t' => 'Apartamentos', 'valor' => '3010'],
                // Região de Vimioso: dá a proporção apartamento/moradia quando o concelho não a tem.
                ['geocod' => '11E', 'geodsg' => 'Terras de Trás-os-Montes', 'dim_3' => '1', 'dim_3_t' => 'Apartamentos', 'valor' => '600'],
                ['geocod' => '11E', 'geodsg' => 'Terras de Trás-os-Montes', 'dim_3' => '2', 'dim_3_t' => 'Moradias', 'valor' => '400'],
                ['geocod' => '11E', 'geodsg' => 'Terras de Trás-os-Montes', 'dim_3' => 'T', 'dim_3_t' => 'Total', 'valor' => '500'],
            ]));
        }

        if ($varcd === config('valuation.ine.sales')) {
            return Http::response(respostaIne('1.º Trimestre de 2026', [
                ['geocod' => '1A01111', 'geodsg' => 'Sintra', 'dim_3' => 'T', 'valor' => '2908'],
                ['geocod' => '1A01111', 'geodsg' => 'Sintra', 'dim_3' => '2', 'valor' => '3100'],
                ['geocod' => '1A0111103', 'geodsg' => 'Colares', 'dim_3' => 'T', 'valor' => '3900'],
                ['geocod' => '11E0411', 'geodsg' => 'Vimioso', 'dim_3' => 'T', 'valor' => '520'],
                ['geocod' => '2000101', 'geodsg' => 'Corvo', 'dim_3' => 'T', 'valor' => '700'],
                ['geocod' => '1A0', 'geodsg' => 'Grande Lisboa', 'dim_3' => 'T', 'valor' => '4000'],
                ['geocod' => '9Z9999901', 'geodsg' => 'Freguesia órfã', 'dim_3' => 'T', 'valor' => '1'],
            ]));
        }

        return Http::response('não esperado', 404);
    });
}

beforeEach(fn () => PropertyCache::flush());

it('importa concelhos por tipo, cai para as vendas ajustadas ao tipo quando a avaliação bancária é confidencial, e traz as freguesias', function () {
    fakeIne();

    $this->artisan('valuation:import-ine')->assertSuccessful();

    $sintraApt = ReferencePrice::where(['city' => 'Sintra', 'locality' => '', 'property_type' => 'apartment'])->first();
    $sintraCasa = ReferencePrice::where(['city' => 'Sintra', 'locality' => '', 'property_type' => 'house'])->first();
    $vimioso = ReferencePrice::where(['city' => 'Vimioso', 'locality' => '', 'property_type' => 'apartment'])->first();
    $vimiosoCasa = ReferencePrice::where(['city' => 'Vimioso', 'locality' => '', 'property_type' => 'house'])->first();
    $colares = ReferencePrice::where(['city' => 'Sintra', 'locality' => 'Colares', 'property_type' => 'house'])->first();
    $corvo = ReferencePrice::where(['city' => 'Corvo', 'locality' => ''])->orderBy('property_type')->pluck('price_per_m2', 'property_type');

    expect($sintraApt->price_per_m2)->toBe('2969.00')
        ->and($sintraApt->source)->toBe('ine')
        ->and($sintraApt->notes)->toContain('avaliação bancária', 'Julho de 2026')
        ->and($sintraCasa->price_per_m2)->toBe('2868.00')
        // Vimioso só tem vendas (520): apartamentos ×600/500, moradias ×400/500, pela região.
        ->and($vimioso->price_per_m2)->toBe('624.00')
        ->and($vimiosoCasa->price_per_m2)->toBe('416.00')
        ->and($vimioso->notes)->toContain('vendas', '1.º Trimestre de 2026', 'ajustado ao tipo', '×1,20')
        // Colares (Sintra) só tem vendas (3900): moradias ×2868/2957, pelo concelho.
        ->and($colares->price_per_m2)->toBe('3783.00')
        ->and($colares->notes)->toContain('freguesia', 'ajustado ao tipo')
        // Corvo: sem avaliação bancária no concelho nem na região — fica igual nos dois tipos.
        ->and($corvo->all())->toBe(['apartment' => '700.00', 'house' => '700.00']);

    // Regiões, totais, valores de outros domicílios e freguesias sem concelho ficam de fora; terrenos nunca vêm do INE.
    expect(ReferencePrice::whereIn('city', ['Algarve', 'Grande Lisboa'])->count())->toBe(0)
        ->and(ReferencePrice::where('locality', 'Freguesia órfã')->count())->toBe(0)
        ->and(ReferencePrice::where('property_type', 'land')->count())->toBe(0)
        ->and(ReferencePrice::count())->toBe(8);
});

it('não pisa os valores escritos à mão e é idempotente', function () {
    fakeIne();
    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2500, 'source' => 'manual']);

    $this->artisan('valuation:import-ine')->assertSuccessful();
    $this->artisan('valuation:import-ine')->assertSuccessful();

    $manual = ReferencePrice::where(['city' => 'Sintra', 'locality' => '', 'property_type' => 'apartment'])->first();

    expect($manual->price_per_m2)->toBe('2500.00')
        ->and($manual->source)->toBe('manual')
        ->and(ReferencePrice::count())->toBe(8);
});

it('a tabela do simulador reflete a importação: freguesia com valor próprio, senão o concelho', function () {
    fakeIne();
    expect(Valuation::table())->toBe([]); // fica em cache vazia…

    $this->artisan('valuation:import-ine')->assertSuccessful();

    $sintra = Valuation::table()['Sintra']; // …e a importação limpa-a.

    expect($sintra['types']['apartment'])->toMatchArray(['ppm2' => 2969.0, 'source' => 'ine'])
        ->and($sintra['localities']['Colares']['apartment']['ppm2'])->toBe(3916.0)
        ->and(Valuation::estimate('Sintra', 'house', 100, 'good', 'Colares'))->toMatchArray(['mid' => 378000, 'place' => 'Colares, Sintra'])
        ->and(Valuation::estimate('Sintra', 'house', 100, 'good', 'Freguesia inexistente'))->toMatchArray(['mid' => 287000, 'place' => 'Sintra']);
});

it('se o INE não responder, falha sem tocar na tabela', function () {
    Http::fake(['www.ine.pt/*' => Http::response('em baixo', 503)]);
    ReferencePrice::create(['city' => 'Sintra', 'property_type' => 'apartment', 'price_per_m2' => 2500]);

    $this->artisan('valuation:import-ine')->assertFailed();

    expect(ReferencePrice::count())->toBe(1);
});
