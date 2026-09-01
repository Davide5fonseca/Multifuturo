<?php

namespace App\Models;

use App\Enums\BusinessType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imóvel — gerido no backoffice (/admin), que escreve nesta tabela; o site
 * lê daqui.
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
 * @property bool $price_visible
 * @property bool $is_sold
 * @property bool $off_market
 * @property ?string $internal_name
 * @property ?string $typology
 * @property ?string $building_name
 * @property ?string $status_reason
 * @property ?string $address
 * @property ?string $street_number
 * @property array<string, mixed> $admin
 * @property array<int, array<string, mixed>> $documents
 * @property ?int $floor_number
 * @property ?int $build_year
 * @property ?string $energy_rating
 * @property ?Carbon $published_at primeira vez que ficou publicável (alertas)
 * @property ?string $crm_property_url
 * @property ?string $video_url
 * @property ?string $virtual_tour_url
 * @property ?string $floorplan_url
 * @property array<string, array<string, mixed>> $translations título, palavras-chave, descrições e textos por canal, por idioma (separador Descrições)
 * @property array<int, array<string, mixed>> $photos
 * @property array<int, string> $features
 * @property ?array<string, ?string> $broker
 * @property string $slug
 * @property string $payload_hash
 * @property ?Carbon $crm_updated_at
 * @property bool $is_active
 * @property bool $is_exclusive
 * @property bool $is_featured
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    // Apagar um imóvel vai para a reciclagem, não para o vazio: uma angariação
    // é trabalho de semanas e não há cópias de segurança para lá ir buscar.
    use SoftDeletes;

    /** Valores do "Actual" — o estado interno da angariação (jsonb admin.status). */
    public const STATUS_ACTIVE = 'Ativa';

    public const STATUS_INACTIVE = 'Inativa';

    public const STATUS_PENDING = 'Pendente';

    /** Pela ordem do CRM. Só "Ativa" chega ao site. */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_PENDING];

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
            'price_visible' => 'boolean',
            'is_active' => 'boolean',
            'is_sold' => 'boolean',
            'off_market' => 'boolean',
            'is_exclusive' => 'boolean',
            'is_featured' => 'boolean',
            'translations' => 'array',
            'photos' => 'array',
            'features' => 'array',
            'details' => 'array',
            'broker' => 'array',
            'admin' => 'array',
            'documents' => 'array',
            'crm_updated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Pode ser mostrado no site? Publicado, não vendido e não retirado do
     * mercado — a mesma regra do scope active(), para uma linha isolada.
     */
    public function isPublishable(): bool
    {
        return $this->is_active
            && ! $this->is_sold
            && ! $this->off_market
            && $this->internalStatus() === self::STATUS_ACTIVE
            && ! $this->trashed();
    }

    /** Estado interno da angariação ("Actual" no CRM). */
    public function internalStatus(): string
    {
        return (string) (data_get($this->admin, 'status') ?: self::STATUS_ACTIVE);
    }

    public function isInactive(): bool
    {
        return $this->internalStatus() === self::STATUS_INACTIVE;
    }

    public function isPending(): bool
    {
        return $this->internalStatus() === self::STATUS_PENDING;
    }

    /** As fichas resolvem-se pelo slug, nunca pelo id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<PropertyActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(PropertyActivity::class);
    }

    /** @return HasMany<PropertyView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(PropertyView::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * O que o site pode mostrar: publicado, não vendido e não retirado do mercado.
     * (Os três estados vêm do registo do imóvel no backoffice.)
     *
     * @param  Builder<Property>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_sold', false)
            ->where('off_market', false)
            // "Actual" (o estado interno da angariação): só uma ficha "Ativa" chega
            // ao site — "Inativa" e "Pendente" ficam de fora, mesmo que o "Visível no
            // website" tenha ficado ligado. As fichas antigas não têm o campo —
            // COALESCE trata-as como ativas.
            ->whereRaw("COALESCE(admin->>'status', ?) = ?", [self::STATUS_ACTIVE, self::STATUS_ACTIVE]);
    }

    /** @param  Builder<Property>  $query */
    public function scopeForSale(Builder $query): Builder
    {
        return $query->whereIn('business_type', BusinessType::forListing('buy'));
    }

    /** @param  Builder<Property>  $query */
    public function scopeForRent(Builder $query): Builder
    {
        return $query->whereIn('business_type', BusinessType::forListing('rent'));
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

    /** Descrição curta (partilhas e resumo), com fallback de idioma. */
    protected function shortDescription(): Attribute
    {
        return Attribute::get(fn () => $this->translation('short_description'));
    }

    /** Descrição SEO — a meta description escrita à mão. */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn () => $this->translation('seo_description'));
    }

    /** Texto formatado para o website (HTML). Passa sempre por Html::clean() antes de sair. */
    protected function websiteHtml(): Attribute
    {
        return Attribute::get(fn () => $this->translation('website_html'));
    }

    /**
     * Palavras-chave da meta keywords. Guardadas como lista; aceita-se também
     * texto separado por vírgulas vindo de importações.
     *
     * @return array<int, string>
     */
    public function keywords(?string $locale = null): array
    {
        $raw = $this->translationRaw('keywords', $locale);
        $lista = is_array($raw) ? $raw : (preg_split('/,/', (string) $raw) ?: []);

        return array_values(array_filter(array_map(fn ($k) => trim((string) $k), $lista), fn ($k) => $k !== ''));
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

    /** URL absoluto da capa — para a lista do backoffice, og:image e JSON-LD. */
    public function coverPhotoUrl(): ?string
    {
        return self::photoUrl($this->cover_photo['url'] ?? null);
    }

    /**
     * As fotos carregadas no backoffice ficam guardadas como "/storage/…" (caminho
     * relativo à raiz do site); as do CRM já vinham absolutas. Onde é preciso um
     * URL completo — Filament, og:image, JSON-LD — resolve-se com o APP_URL.
     */
    public static function photoUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }

    public function translation(string $key, ?string $locale = null): ?string
    {
        $valor = $this->translationRaw($key, $locale);

        return is_string($valor) && ! self::isBlankText($valor) ? $valor : null;
    }

    /**
     * Um texto só com etiquetas vazias ("<p></p>", que é o que o editor HTML
     * guarda quando não se escreve nada) conta como vazio.
     */
    public static function isBlankText(mixed $valor): bool
    {
        if (is_array($valor)) {
            return $valor === [];
        }

        return $valor === null || trim(strip_tags((string) $valor)) === '';
    }

    /** Valor tal como está guardado (texto ou lista), com fallback para o idioma por defeito. */
    public function translationRaw(string $key, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        $valor = $this->translations[$locale][$key] ?? null;
        if (self::isBlankText($valor)) {
            $valor = $this->translations[$fallback][$key] ?? null;
        }

        return $valor;
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
