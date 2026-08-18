<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\PropertyCache;
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
        return view('pages.valuation');
    }

    public function about(): View
    {
        return $this->placeholder(__('ui.nav.about'));
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function privacy(): View
    {
        return $this->placeholder(__('ui.footer.privacy'));
    }

    public function terms(): View
    {
        return $this->placeholder(__('ui.footer.terms'));
    }

    public function cookies(): View
    {
        return $this->placeholder(__('ui.footer.cookies'));
    }

    /** Página provisória: título + aviso. Fica com noindex até ter conteúdo real. */
    private function placeholder(string $title): View
    {
        return view('pages.placeholder', ['title' => $title]);
    }
}
