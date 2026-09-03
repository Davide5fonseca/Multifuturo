<?php

namespace App\Livewire;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\PropertyCache;
use App\Support\PropertyFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

    /**
     * Blocos de resultados já carregados nesta página (scroll infinito). Não
     * vai para o URL: quem partilha o endereço parte do princípio, e os
     * motores de busca continuam a ver a paginação normal.
     */
    public int $batches = 1;

    /** A página a que esses blocos pertencem — trocar de página recomeça a contagem. */
    public int $batchesPage = 1;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'tipologia', except: '')]
    public string $bedrooms = '';

    #[Url(as: 'distrito', except: '')]
    public string $district = '';

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
        // Filtro novo (ou página nova): recomeça-se com um bloco.
        $this->batches = 1;
        $this->sanitize();
        // Escolher um distrito recomeça o concelho e a freguesia; trocar de
        // concelho recomeça a freguesia — senão ficavam pares impossíveis.
        if ($name === 'district') {
            $this->city = '';
            $this->locality = '';
        }
        if ($name === 'city') {
            $this->locality = '';
        }
    }

    /** Scroll infinito: mais um bloco de resultados, com teto de segurança. */
    public function loadMore(): void
    {
        $this->batches = min($this->batches + 1, 8);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'bedrooms', 'district', 'city', 'locality', 'priceMin', 'priceMax', 'areaMin', 'features']);
        $this->sort = 'recent';
        $this->batches = 1;
        $this->resetPage();
    }

    /** Os valores vêm do URL — fonte não confiável. Tudo é limitado e normalizado. */
    private function sanitize(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 80);
        $this->type = mb_substr(trim($this->type), 0, 64);
        $this->district = mb_substr(trim($this->district), 0, 96);
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

    /**
     * Finalidades que entram nesta listagem. /comprar mostra venda, trespasse e
     * permuta; /arrendar mostra arrendamento ao ano e de curto prazo; o
     * "arrendamento / venda" aparece nas duas.
     *
     * @return array<int, string>
     */
    private function businessTypes(): array
    {
        return BusinessType::forListing(BusinessType::from($this->businessType)->routeName());
    }

    /** @return Builder<Property> */
    private function query(): Builder
    {
        $q = Property::query()->active()->whereIn('business_type', $this->businessTypes());

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

        PropertyFilters::apply($q, $this->criteria());

        return match ($this->sort) {
            'price_asc' => $q->orderByRaw('price ASC NULLS LAST')->orderByDesc('id'),
            'price_desc' => $q->orderByRaw('price DESC NULLS LAST')->orderByDesc('id'),
            default => $q->orderByRaw('crm_updated_at DESC NULLS LAST')->orderByDesc('id'),
        };
    }

    /**
     * Resultados em cache. A chave inclui todos os filtros, a página e quantos
     * blocos já foram carregados; a cache é limpa sempre que a carteira muda.
     *
     * O scroll infinito acumula a partir da página atual: a paginação numerada
     * continua a existir (sem JavaScript e para os motores de busca), e por isso
     * o paginador é montado à mão — os itens são os N blocos já carregados, mas
     * as ligações das páginas continuam a contar PER_PAGE por página.
     */
    #[Computed]
    public function properties(): LengthAwarePaginator
    {
        $page = $this->getPage();
        $key = 'listing:'.md5(json_encode([
            $this->businessType, $this->search, $this->type, $this->bedrooms, $this->district, $this->city, $this->locality,
            $this->priceMin, $this->priceMax, $this->areaMin, $this->features, $this->sort, $page, $this->batches,
        ]));

        return PropertyCache::remember($key, function () use ($page): LengthAwarePaginator {
            $query = $this->query();
            $total = (clone $query)->toBase()->getCountForPagination();
            $offset = ($page - 1) * self::PER_PAGE;
            $items = $query->skip($offset)->take(self::PER_PAGE * $this->batches)->get();

            return new LengthAwarePaginator($items, $total, self::PER_PAGE, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        });
    }

    /** Ainda há resultados por mostrar depois dos que já estão no ecrã? */
    public function hasMore(): bool
    {
        $results = $this->properties();

        return ($results->firstItem() ?? 0) + $results->count() < $results->total();
    }

    /**
     * Opções dos filtros a partir da carteira ativa desta finalidade.
     *
     * @return array{types: array<int,string>, districts: array<int,string>, cities: array<int,string>, localities: array<int,string>, features: array<int,string>, bedrooms: array<int,int>}
     */
    #[Computed]
    public function options(): array
    {
        $base = PropertyCache::remember('options:'.$this->businessType, function () {
            $active = Property::query()->active()->whereIn('business_type', $this->businessTypes());

            $features = collect((clone $active)->pluck('features'))->flatten()->filter()->countBy()->sortDesc()->keys()->take(20)->values()->all();

            return [
                'types' => (clone $active)->whereNotNull('property_type')->distinct()->orderBy('property_type')->pluck('property_type')->all(),
                'districts' => (clone $active)->whereNotNull('district')->distinct()->orderBy('district')->pluck('district')->all(),
                'cities' => (clone $active)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all(),
                'features' => $features,
                'bedrooms' => (clone $active)->whereNotNull('bedrooms')->distinct()->orderBy('bedrooms')->pluck('bedrooms')->map(fn ($b) => (int) $b)->all(),
            ];
        });

        // Escolhido um distrito, só se oferecem os concelhos que lá existem.
        if ($this->district !== '') {
            $base['cities'] = PropertyCache::remember('cities:'.$this->businessType.':'.mb_strtolower($this->district), fn () => Property::query()->active()
                ->whereIn('business_type', $this->businessTypes())
                ->whereRaw('LOWER(district) = ?', [mb_strtolower($this->district)])
                ->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all());
        }

        $base['localities'] = $this->city === '' ? [] : PropertyCache::remember('localities:'.$this->businessType.':'.mb_strtolower($this->city), fn () => Property::query()->active()
            ->whereIn('business_type', $this->businessTypes())
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($this->city)])
            ->whereNotNull('locality')->distinct()->orderBy('locality')->pluck('locality')->all());

        return $base;
    }

    /**
     * Os filtros ativos no formato partilhado com os alertas de imóveis
     * (App\Support\PropertyFilters) — a pesquisa e o formulário "avise-me"
     * usam exatamente os mesmos critérios.
     *
     * @return array<string, mixed>
     */
    public function criteria(): array
    {
        return PropertyFilters::sanitize([
            'type' => $this->type,
            'bedrooms' => $this->bedrooms,
            'district' => $this->district,
            'city' => $this->city,
            'locality' => $this->locality,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'area_min' => $this->areaMin,
            'features' => $this->features,
        ]);
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->type !== '' || $this->bedrooms !== '' || $this->district !== '' || $this->city !== ''
            || $this->locality !== '' || $this->priceMin !== '' || $this->priceMax !== '' || $this->areaMin !== '' || $this->features !== [];
    }

    public function render(): View
    {
        // As ligações da paginação chamam gotoPage() (não passam por updated()):
        // é aqui que se apanha a mudança de página e se recomeça com um bloco.
        if ($this->getPage() !== $this->batchesPage) {
            $this->batches = 1;
            $this->batchesPage = $this->getPage();
        }

        return view('livewire.property-listing', [
            'businessTypeEnum' => BusinessType::from($this->businessType),
        ]);
    }
}
