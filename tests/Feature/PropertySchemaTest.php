<?php

/*
 * Fase 2 — schema properties e model Property.
 */

use App\Enums\BusinessType;
use App\Models\Property;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('cria a tabela properties com as colunas previstas', function () {
    $expected = [
        'internal_id', 'reference', 'price', 'currency', 'business_type', 'property_type',
        'property_condition', 'bedrooms', 'bathrooms', 'house_area', 'plot_area', 'gross_area',
        'country', 'district', 'city', 'locality', 'zone', 'zipcode', 'lat', 'lon', 'gmap_visible',
        'floor_number', 'build_year', 'energy_rating', 'crm_property_url', 'video_url',
        'virtual_tour_url', 'floorplan_url', 'translations', 'photos', 'features', 'broker',
        'slug', 'payload_hash', 'crm_updated_at', 'is_active', 'is_exclusive', 'is_featured',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('properties', $column))->toBeTrue("falta a coluna {$column}");
    }
});

it('não tem nenhuma coluna para dados do proprietário (Owner)', function () {
    foreach (Schema::getColumnListing('properties') as $column) {
        expect(str_contains(strtolower($column), 'owner'))->toBeFalse("coluna suspeita: {$column}");
    }
});

it('usa jsonb e tem o índice GIN em features', function () {
    $types = collect(Schema::getColumns('properties'))->pluck('type', 'name');

    expect($types['translations'])->toBe('jsonb')
        ->and($types['photos'])->toBe('jsonb')
        ->and($types['features'])->toBe('jsonb')
        ->and($types['broker'])->toBe('jsonb');

    $indexes = collect(Schema::getIndexes('properties'));

    expect($indexes->firstWhere('name', 'properties_features_gin_idx')['type'] ?? null)->toBe('gin')
        ->and($indexes->firstWhere('name', 'properties_active_business_price_idx')['columns'])->toBe(['is_active', 'business_type', 'price'])
        ->and($indexes->firstWhere('name', 'properties_city_locality_idx')['columns'])->toBe(['city', 'locality'])
        ->and($indexes->contains(fn ($i) => $i['columns'] === ['slug'] && $i['unique']))->toBeTrue()
        ->and($indexes->contains(fn ($i) => $i['columns'] === ['internal_id'] && $i['unique']))->toBeTrue();
});

it('impede internal_id e slug duplicados', function () {
    $p = Property::factory()->create();

    expect(fn () => Property::factory()->create(['internal_id' => $p->internal_id]))->toThrow(QueryException::class);
    expect(fn () => Property::factory()->create(['slug' => $p->slug]))->toThrow(QueryException::class);
});

it('faz cast dos campos jsonb, booleanos e da finalidade', function () {
    $p = Property::factory()->create(['features' => ['garagem', 'elevador']])->fresh();

    expect($p->business_type)->toBe(BusinessType::Sale)
        ->and($p->features)->toBe(['garagem', 'elevador'])
        ->and($p->translations)->toHaveKey('pt')
        ->and($p->gmap_visible)->toBeTrue()
        ->and($p->is_active)->toBeTrue()
        ->and($p->title)->toBe($p->translations['pt']['title']);
});

it('filtra por características com o índice GIN', function () {
    Property::factory()->create(['features' => ['garagem', 'elevador']]);
    Property::factory()->create(['features' => ['varanda']]);

    expect(Property::withFeatures(['garagem'])->count())->toBe(1)
        ->and(Property::withFeatures(['garagem', 'elevador'])->count())->toBe(1)
        ->and(Property::withFeatures(['piscina'])->count())->toBe(0);
});

it('tem scopes para ativos, venda e arrendamento', function () {
    Property::factory()->count(2)->create();
    Property::factory()->forRent()->create();
    Property::factory()->inactive()->create();

    expect(Property::active()->count())->toBe(3)
        ->and(Property::forSale()->count())->toBe(3)
        ->and(Property::forRent()->count())->toBe(1)
        ->and(Property::active()->forSale()->count())->toBe(2);
});

it('esconde as coordenadas quando gmap_visible é false', function () {
    $visible = Property::factory()->create();
    $hidden = Property::factory()->withoutMap()->create();

    expect($visible->coordinates)->toBeArray()->toHaveKeys(['lat', 'lon'])
        ->and($hidden->coordinates)->toBeNull()
        ->and($hidden->lat)->not->toBeNull(); // o dado existe na BD; só não é exposto
});

it('gera slugs únicos e legíveis, sem os recalcular depois', function () {
    $slug = Property::generateSlug('Apartamento', 'Cascais', 'MF-2041', '123456');
    expect($slug)->toBe('apartamento-cascais-mf-2041');

    Property::factory()->create(['slug' => $slug]);
    expect(Property::generateSlug('Apartamento', 'Cascais', 'MF-2041', '123456'))->toBe('apartamento-cascais-mf-2041-2');

    // Sem referência cai no internal_id.
    expect(Property::generateSlug(null, null, null, '987'))->toBe('987');
});

it('resolve rotas pelo slug e não pelo id', function () {
    expect((new Property)->getRouteKeyName())->toBe('slug');
});

it('a base de dados de testes é PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});
