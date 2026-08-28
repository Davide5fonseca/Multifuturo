<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Support\PropertyFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Alerta de imóveis: "avise-me quando entrar um imóvel assim".
 *
 * @property string $email
 * @property ?string $name
 * @property string $locale
 * @property string $listing buy | rent
 * @property array<string, mixed> $criteria
 * @property string $token
 * @property ?Carbon $confirmed_at
 * @property ?Carbon $unsubscribed_at
 * @property ?Carbon $last_sent_at
 * @property int $sent_count
 * @property ?string $policy_version
 * @property ?string $ip_hash
 * @property ?string $user_agent
 */
class PropertyAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    /** Confirmado e não cancelado: recebe envios. */
    public function isActive(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    /** Frase com os critérios ("Venda · Sintra · T3+ · ≤ 300 000 €"). */
    public function summary(): string
    {
        return PropertyFilters::summary($this->criteria ?? [], $this->listing);
    }

    /**
     * Os imóveis publicados que encaixam nos critérios — a mesma pesquisa da
     * listagem de onde o alerta veio.
     *
     * @return Builder<Property>
     */
    public function matches(): Builder
    {
        $query = Property::query()->active()->whereIn('business_type', BusinessType::forListing($this->listing));

        return PropertyFilters::apply($query, $this->criteria ?? []);
    }

    /** URL da listagem com estes critérios aplicados. */
    public function listingUrl(): string
    {
        return route($this->listing === 'rent' ? 'rent' : 'buy', ['locale' => $this->locale] + PropertyFilters::urlParams($this->criteria ?? []));
    }
}
