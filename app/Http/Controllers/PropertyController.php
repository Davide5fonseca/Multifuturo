<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyView;
use App\Support\Format;
use App\Support\PropertyCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Ficha de imóvel. Um imóvel desativado (saiu do feed) responde 410 Gone com
 * uma página útil (semelhantes + contacto) em vez de 404: diz aos motores de
 * busca que o URL foi retirado de propósito e mantém o utilizador no site.
 */
class PropertyController extends Controller
{
    public function show(Property $property): View|Response
    {
        $similar = PropertyCache::remember('similar:'.$property->id, fn () => Property::query()
            ->active()
            ->whereKeyNot($property->id)
            ->where('business_type', $property->business_type)
            ->when($property->city, fn ($q) => $q->orderByRaw('CASE WHEN LOWER(city) = ? THEN 0 ELSE 1 END', [mb_strtolower($property->city)]))
            ->when($property->property_type, fn ($q) => $q->orderByRaw('CASE WHEN LOWER(property_type) = ? THEN 0 ELSE 1 END', [mb_strtolower($property->property_type)]))
            ->orderByRaw('crm_updated_at DESC NULLS LAST')
            ->limit(3)
            ->get());

        if (! $property->isPublishable()) {
            return response()->view('pages.property-gone', ['property' => $property, 'similar' => $similar], 410);
        }

        // Métrica agregada por dia (sem IP nem cookies) para o gráfico do backoffice.
        PropertyView::record($property->getKey());

        return view('pages.property', [
            'property' => $property,
            'similar' => $similar,
            'jsonLd' => $this->jsonLd($property),
        ]);
    }

    /**
     * schema.org RealEstateListing. Sem coordenadas quando gmap_visible=false —
     * o acessor coordinates já devolve null nesse caso.
     *
     * @return array<string, mixed>
     */
    private function jsonLd(Property $p): array
    {
        $offer = [
            '@type' => 'Offer',
            'availability' => 'https://schema.org/InStock',
            'businessFunction' => $p->business_type->value === 'rent' ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
        ];
        // Preço escondido no backoffice não entra no JSON-LD (seria contraditório com a ficha).
        if ($p->price !== null && $p->price_visible) {
            $offer['price'] = (string) $p->price;
            $offer['priceCurrency'] = $p->currency;
        }

        $address = array_filter([
            '@type' => 'PostalAddress',
            'addressLocality' => $p->city,
            'addressRegion' => $p->district,
            'postalCode' => $p->zipcode,
            'addressCountry' => $p->country ?: 'PT',
        ]);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? '')),
            'url' => route('property.show', $p),
            'identifier' => $p->reference ?? $p->internal_id,
            'datePosted' => $p->crm_updated_at?->toDateString(),
            'image' => array_values(array_map(fn ($ph) => $ph['url'], array_slice($p->photos ?? [], 0, 10))),
            'offers' => $offer,
            'address' => $address,
            'provider' => [
                '@type' => 'RealEstateAgent',
                'name' => config('agency.name'),
                'url' => route('home'),
            ],
        ];

        if ($texto = ($p->description ?: strip_tags((string) $p->website_html))) {
            $data['description'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($texto))), 0, 500);
        }
        if ($p->bedrooms !== null) {
            $data['numberOfRooms'] = $p->bedrooms;
        }
        if ($p->house_area ?? $p->gross_area) {
            $data['floorSize'] = ['@type' => 'QuantitativeValue', 'value' => (float) ($p->house_area ?? $p->gross_area), 'unitCode' => 'MTK'];
        }
        if ($coords = $p->coordinates) {
            $data['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $coords['lat'], 'longitude' => (float) $coords['lon']];
        }

        return array_filter($data, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}
