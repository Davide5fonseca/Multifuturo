<?php

namespace App\Models;

use App\Enums\BusinessType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imóvel — réplica local de um registo do CASAFARI CRM.
 *
 * Só o comando de sync escreve nesta tabela. O site lê daqui e nunca do CRM.
 *
 * @property int $id
 * @property string $internal_id
 * @property ?string $reference
 * @property ?string $price
 * @property string $currency
 * @property BusinessType $business_type
 * @property ?string $property_type
 * @property ?string $property_condition
 * @property ?int $bedrooms
 * @property ?int $bathrooms
 * @property ?string $house_area
 * @property ?string $plot_area
 * @property ?string $gross_area
 * @property string $country
 * @property ?string $district
 * @property ?string $city
 * @property ?string $locality
 * @property ?string $zone
 * @property ?string $zipcode
 * @property ?string $lat
 * @property ?string $lon
 * @property bool $gmap_visible
 * @property ?int $floor_number
 * @property ?int $build_year
 * @property ?string $energy_rating
 * @property ?string $crm_property_url
 * @property ?string $video_url
 * @property ?string $virtual_tour_url
 * @property ?string $floorplan_url
 * @property array<string, array<string, ?string>> $translations
 * @property array<int, array<string, mixed>> $photos
 * @property array<int, string> $features
 * @property ?array<string, ?string> $broker
 * @property string $slug
 * @property string $payload_hash
 * @property ?Carbon $crm_updated_at
 * @property bool $is_active
 * @property bool $is_exclusive
 * @property bool $is_featured
 * @property ?Carbon $synced_at
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    /**
     * Sem $fillable restritivo: a escrita é feita apenas pelo sync a partir de
     * arrays construídos pelo mapper (não de input do utilizador). Owner nunca
     * chega aqui porque o mapper não o produz.
     */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'price' => 'decimal:2',
            'house_area' => 'decimal:2',
            'plot_area' => 'decimal:2',
            'gross_area' => 'decimal:2',
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'floor_number' => 'integer',
            'build_year' => 'integer',
            'gmap_visible' => 'boolean',
            'is_active' => 'boolean',
            'is_exclusive' => 'boolean',
            'is_featured' => 'boolean',
            'translations' => 'array',
            'photos' => 'array',
            'features' => 'array',
            'broker' => 'array',
            'crm_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** As fichas resolvem-se pelo slug, nunca pelo id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** @param  Builder<Property>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<Property>  $query */
    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('business_type', BusinessType::Sale);
    }

    /** @param  Builder<Property>  $query */
    public function scopeForRent(Builder $query): Builder
    {
        return $query->where('business_type', BusinessType::Rent);
    }

    /** @param  Builder<Property>  $query */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Filtra por características usando o índice GIN (features @> '["garagem"]').
     *
     * @param  Builder<Property>  $query
     * @param  array<int, string>  $features
     */
    public function scopeWithFeatures(Builder $query, array $features): Builder
    {
        foreach ($features as $feature) {
            $query->whereRaw('features @> ?::jsonb', [json_encode([$feature])]);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Acessores
    |--------------------------------------------------------------------------
    */

    /** Título no idioma atual, com fallback para o idioma por defeito. */
    protected function title(): Attribute
    {
        return Attribute::get(fn () => $this->translation('title'));
    }

    /** Descrição no idioma atual, com fallback para o idioma por defeito. */
    protected function description(): Attribute
    {
        return Attribute::get(fn () => $this->translation('description'));
    }

    /**
     * Coordenadas só se o proprietário autorizou (gmap_visible). Fora disso o
     * par é null — assim nenhuma view, JSON-LD ou API expõe lat/lon por engano.
     *
     * @return array{lat: string, lon: string}|null
     */
    protected function coordinates(): Attribute
    {
        return Attribute::get(function (): ?array {
            if (! $this->gmap_visible || $this->lat === null || $this->lon === null) {
                return null;
            }

            return ['lat' => $this->lat, 'lon' => $this->lon];
        });
    }

    /** Primeira foto (capa) ou null. */
    protected function coverPhoto(): Attribute
    {
        return Attribute::get(fn () => $this->photos[0] ?? null);
    }

    public function translation(string $key, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        return $this->translations[$locale][$key]
            ?? $this->translations[$fallback][$key]
            ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Slugs estáveis
    |--------------------------------------------------------------------------
    */

    /**
     * Gera um slug único a partir de tipo, concelho e referência — rico em
     * palavras-chave e legível. É chamado UMA vez, na criação; nunca é
     * recalculado quando o título muda no CRM (partiria URLs indexados).
     */
    public static function generateSlug(?string $propertyType, ?string $city, ?string $reference, string $internalId): string
    {
        $parts = array_filter([$propertyType, $city, $reference ?: $internalId]);
        $base = Str::slug(implode(' ', $parts)) ?: Str::slug($internalId) ?: 'imovel';
        $base = Str::limit($base, 150, '');

        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
