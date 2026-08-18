<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Páginas server-rendered. As páginas de listagem e as institucionais são,
 * nesta fase, esqueletos com o layout final; o conteúdo entra nas Fases 4 e 6.
 */
class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function buy(): View
    {
        return view('pages.listing', ['businessType' => 'sale', 'title' => __('ui.listing.buy_title')]);
    }

    public function rent(): View
    {
        return view('pages.listing', ['businessType' => 'rent', 'title' => __('ui.listing.rent_title')]);
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

    /**
     * Página provisória: título + aviso. Fica com noindex até ter conteúdo real.
     */
    private function placeholder(string $title): View
    {
        return view('pages.placeholder', ['title' => $title]);
    }
}
