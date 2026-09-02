<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\PropertyCache;
use App\Support\Valuation;
use App\Support\Zones;
use Illuminate\Contracts\View\View;

/**
 * Páginas server-rendered. As institucionais/legais são esqueletos com o
 * layout final até a Fase 6 lhes dar conteúdo.
 */
class PageController extends Controller
{
    public function home(): View
    {
        // Destaques: is_featured primeiro; se não houver, os mais recentes. Máx. 6.
        $featured = PropertyCache::remember('home:featured', function () {
            $featured = Property::query()->active()->featured()->orderByRaw('crm_updated_at DESC NULLS LAST')->limit(6)->get();

            if ($featured->count() < 3) {
                $featured = $featured->concat(
                    Property::query()->active()->whereKeyNot($featured->modelKeys())->orderByRaw('crm_updated_at DESC NULLS LAST')->limit(6 - $featured->count())->get()
                );
            }

            return $featured;
        });

        $heroImage = config('agency.hero_image') ?: ($featured->first()?->cover_photo['url'] ?? null);

        return view('pages.home', [
            'featured' => $featured,
            'heroImage' => $heroImage,
            'cities' => Zones::cities(),
            // Números da carteira publicada — contam no ecrã, e são verdade.
            'stats' => PropertyCache::remember('home:stats', fn () => [
                'properties' => Property::query()->active()->count(),
                'cities' => Property::query()->active()->whereNotNull('city')->distinct()->count('city'),
                'localities' => Property::query()->active()->whereNotNull('locality')->distinct()->count('locality'),
            ]),
        ]);
    }

    public function buy(): View
    {
        return view('pages.listing', [
            'businessType' => BusinessType::Sale,
            'title' => __('ui.listing.buy_title'),
            'description' => __('ui.listing.buy_description'),
        ]);
    }

    public function rent(): View
    {
        return view('pages.listing', [
            'businessType' => BusinessType::Rent,
            'title' => __('ui.listing.rent_title'),
            'description' => __('ui.listing.rent_description'),
        ]);
    }

    public function valuation(): View
    {
        return view('pages.valuation', ['table' => Valuation::table()]);
    }

    public function about(): View
    {
        return $this->legal('about');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    public function terms(): View
    {
        return $this->legal('terms');
    }

    public function cookies(): View
    {
        return $this->legal('cookies');
    }

    /**
     * Documento legal/institucional a partir de lang/pt/legal.php, com os dados
     * da agência substituídos nos textos.
     */
    private function legal(string $key): View
    {
        $agency = config('agency');

        return view('pages.legal', [
            'key' => $key,
            'replacements' => [
                'name' => $agency['name'],
                // Cada dado em falta produz a frase certa, não um buraco ("AMI n.º ", "com sede em .").
                'ami' => filled($agency['ami']) ? 'n.º '.$agency['ami'] : '(número por atribuir)',
                'address' => (string) $agency['address'],
                'seat' => filled($agency['address']) ? ', com sede em '.$agency['address'] : '',
                'email' => (string) $agency['email'],
                'phone' => (string) $agency['phone'],
                'contact_line' => implode(' · ', array_filter([
                    filled($agency['phone']) ? 'Telefone: '.$agency['phone'] : null,
                    filled($agency['email']) ? 'Email: '.$agency['email'] : null,
                ])),
                'version' => $agency['privacy_policy_version'],
                'consent_cookie' => config('consent.cookie'),
            ],
        ]);
    }
}
