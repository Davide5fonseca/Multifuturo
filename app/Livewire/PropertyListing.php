<?php

namespace App\Livewire;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\PropertyCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de imóveis (/comprar e /arrendar) com filtros.
 *
 * - Todos os filtros vivem na query string (#[Url]) — URLs partilháveis e
 *   indexáveis; o primeiro render é server-side com os filtros aplicados.
 * - Paginação real (não infinite scroll).
 * - Resultados e opções de filtro em cache (PropertyCache), invalidada no sync.
 * - A finalidade (venda/arrendamento) é fixa por rota e não é um filtro.
 */
class PropertyListing extends Component
{
    use WithPagination;

    public const PER_PAGE = 12;

    #[Locked]
    public string $businessType = 'sale';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'tipologia', except: '')]
    public string $bedrooms = '';

    #[Url(as: 'concelho', except: '')]
    public string $city = '';

    #[Url(as: 'freguesia', except: '')]
    public string $locality = '';

    #[Url(as: 'preco_min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'preco_max', except: '')]
    public string $priceMax = '';

    #[Url(as: 'area_min', except: '')]
    public string $areaMin = '';

    /** @var array<int, string> */
    #[Url(as: 'caracteristicas', except: [])]
    public array $features = [];

    #[Url(as: 'ordenar', except: 'recent')]
    public string $sort = 'recent';

    public function mount(string $businessType = 'sale'): void
    {
        $this->businessType = BusinessType::tryFrom($businessType)?->value ?? 'sale';
        $this->sanitize();
    }

    /** Qualquer alteração de filtro volta à primeira página. */
    public function updated(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
        $this->sanitize();
        if ($name === 'city') {
            $this->locality = '';
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'bedrooms', 'city', 'locality', 'priceMin', 'priceMax', 'areaMin', 'features']);
        $this->sort = 'recent';
        $this->resetPage();
    }

    /** Os valores vêm do URL — fonte não confiável. Tudo é limitado e normalizado. */
    private function sanitize(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 80);
        $this->type = mb_substr(trim($this->type), 0, 64);
        $this->city = mb_substr(trim($this->city), 0, 96);
        $this->locality = mb_substr(trim($this->locality), 0, 96);
        $this->bedrooms = ctype_digit($this->bedrooms) && (int) $this->bedrooms <= 20 ? $this->bedrooms : '';
        $this->priceMin = $this->digits($this->priceMin);
        $this->priceMax = $this->digits($this->priceMax);
        $this->areaMin = $this->digits($this->areaMin);
        $this->features = array_values(array_unique(array_filter(
            array_map(fn ($f) => mb_substr(mb_strtolower(trim((string) $f)), 0, 96), array_slice($this->features, 0, 12))
        )));
        if (! in_array($this->sort, ['recent', 'price_asc', 'price_desc'], true)) {
            $this->sort = 'recent';
        }
    }

    private function digits(string $value): string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if ($value === '' || strlen($value) > 12) {
            return '';
        }

        return ltrim($value, '0');
    }

    /** @return Builder<Property> */
    private function query(): Builder
    {
        $q = Property::query()->active()->where('business_type', $this->businessType);

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($this->search)).'%';
            $q->where(function (Builder $w) use ($term) {
                $w->whereRaw('LOWER(reference) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(locality) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(zone) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(translations->'pt'->>'title') LIKE ?", [$term]);
            });
        }

        if ($this->type !== '') {
            $q->whereRaw('LOWER(property_type) = ?', [mb_strtolower($this->type)]);
        }
        if ($this->bedrooms !== '') {
            $q->where('bedrooms', '>=', (int) $this->bedrooms);
        }
        if ($this->city !== '') {
            $q->whereRaw('LOWER(city) = ?', [mb_strtolower($this->city)]);
        }
        if ($this->locality !== '') {
            $q->whereRaw('LOWER(locality) = ?', [mb_strtolower($this->locality)]);
        }
        if ($this->priceMin !== '') {
            $q->where('price', '>=', (int) $this->priceMin);
        }
        if ($this->priceMax !== '') {
            $q->where('price', '<=', (int) $this->priceMax);
        }
        if ($this->areaMin !== '') {
            $q->where(fn (Builder $w) => $w->where('house_area', '>=', (int) $this->areaMin)->orWhere('gross_area', '>=', (int) $this->areaMin));
        }
        if ($this->features !== []) {
            $q->withFeatures($this->features);
        }

        return match ($this->sort) {
            'price_asc' => $q->orderByRaw('price ASC NULLS LAST')->orderByDesc('id'),
            'price_desc' => $q->orderByRaw('price DESC NULLS LAST')->orderByDesc('id'),
            default => $q->orderByRaw('crm_updated_at DESC NULLS LAST')->orderByDesc('id'),
        };
    }

    /**
     * Página de resultados em cache. A chave inclui todos os filtros e a página;
     * a cache é limpa no fim de cada sync com alterações.
     */
    #[Computed]
    public function properties(): LengthAwarePaginator
    {
        $page = $this->getPage();
        $key = 'listing:'.md5(json_encode([
            $this->businessType, $this->search, $this->type, $this->bedrooms, $this->city, $this->locality,
            $this->priceMin, $this->priceMax, $this->areaMin, $this->features, $this->sort, $page,
        ]));

        return PropertyCache::remember($key, fn () => $this->query()->paginate(self::PER_PAGE, ['*'], 'page', $page));
    }

    /**
     * Opções dos filtros a partir da carteira ativa desta finalidade.
     *
     * @return array{types: array<int,string>, cities: array<int,string>, localities: array<int,string>, features: array<int,string>, bedrooms: array<int,int>}
     */
    #[Computed]
    public function options(): array
    {
        $base = PropertyCache::remember('options:'.$this->businessType, function () {
            $active = Property::query()->active()->where('business_type', $this->businessType);

            $features = collect((clone $active)->pluck('features'))->flatten()->filter()->countBy()->sortDesc()->keys()->take(20)->values()->all();

            return [
                'types' => (clone $active)->whereNotNull('property_type')->distinct()->orderBy('property_type')->pluck('property_type')->all(),
                'cities' => (clone $active)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all(),
                'features' => $features,
                'bedrooms' => (clone $active)->whereNotNull('bedrooms')->distinct()->orderBy('bedrooms')->pluck('bedrooms')->map(fn ($b) => (int) $b)->all(),
            ];
        });

        $base['localities'] = $this->city === '' ? [] : PropertyCache::remember('localities:'.$this->businessType.':'.mb_strtolower($this->city), fn () => Property::query()->active()
            ->where('business_type', $this->businessType)
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($this->city)])
            ->whereNotNull('locality')->distinct()->orderBy('locality')->pluck('locality')->all());

        return $base;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->type !== '' || $this->bedrooms !== '' || $this->city !== ''
            || $this->locality !== '' || $this->priceMin !== '' || $this->priceMax !== '' || $this->areaMin !== '' || $this->features !== [];
    }

    public function render(): View
    {
        return view('livewire.property-listing', [
            'businessTypeEnum' => BusinessType::from($this->businessType),
        ]);
    }
}
