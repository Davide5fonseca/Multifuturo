<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\Format;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Comparador: até três imóveis lado a lado. Os slugs vivem no localStorage do
 * visitante (como os favoritos) e chegam aqui em ?slugs=a,b,c — nada é guardado
 * no servidor. As linhas da tabela são construídas aqui para a vista ser só
 * apresentação, e as que ficam vazias em todos os imóveis não aparecem.
 */
class CompareController extends Controller
{
    public const MAX = 3;

    public function __invoke(Request $request): View
    {
        $slugs = collect(explode(',', (string) $request->query('slugs', '')))
            ->map(fn ($s) => mb_substr(trim($s), 0, 191))
            ->filter(fn ($s) => $s !== '' && preg_match('/^[a-z0-9-]+$/', $s))
            ->unique()
            ->take(self::MAX)
            ->values();

        $properties = $slugs->isEmpty()
            ? collect()
            : Property::query()->active()->whereIn('slug', $slugs)->get()
                ->sortBy(fn (Property $p) => $slugs->search($p->slug))
                ->values();

        return view('pages.compare', [
            'properties' => $properties,
            'rows' => $this->rows($properties),
            'requested' => $request->has('slugs'),
        ]);
    }

    /**
     * As linhas da comparação, pela ordem em que interessam a quem compra.
     * Cada linha traz um valor por imóvel; as vazias em todos são descartadas.
     *
     * @param  Collection<int, Property>  $properties
     * @return array<string, array<int, ?string>>
     */
    private function rows($properties): array
    {
        $linhas = [
            __('ui.property.price') => fn (Property $p) => Format::price($p->price, $p->currency, $p->business_type, $p->price_visible),
            __('ui.property.type') => fn (Property $p) => $p->property_type,
            __('ui.property.typology') => fn (Property $p) => ($p->typology && $p->typology !== 'Não aplicável') ? $p->typology : Format::typology($p->bedrooms),
            __('ui.property.wc') => fn (Property $p) => $p->bathrooms,
            __('ui.property.house_area') => fn (Property $p) => Format::area($p->house_area),
            __('ui.property.gross_area') => fn (Property $p) => Format::area($p->gross_area),
            __('ui.property.plot_area') => fn (Property $p) => Format::area($p->plot_area),
            __('ui.property.location') => fn (Property $p) => Format::location($p->locality, $p->city, $p->district),
            __('ui.property.floor') => fn (Property $p) => $p->floor_number,
            __('ui.property.build_year') => fn (Property $p) => $p->build_year,
            __('ui.property.condition') => fn (Property $p) => $p->property_condition,
            __('ui.property.energy_rating') => fn (Property $p) => $p->energy_rating,
            __('ui.property.features') => fn (Property $p) => $p->features ? implode(', ', $p->features) : null,
        ];

        $rows = [];

        foreach ($linhas as $label => $valor) {
            $valores = $properties->map(fn (Property $p) => filled($valor($p)) ? (string) $valor($p) : null)->all();

            if (array_filter($valores) !== []) {
                $rows[$label] = $valores;
            }
        }

        return $rows;
    }
}
