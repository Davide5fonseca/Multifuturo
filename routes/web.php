<?php

use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ZoneController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
|
| Todas vivem dentro de um prefixo de idioma (/pt/comprar, /en/comprar). O
| idioma é um parâmetro de rota com valor por omissão, posto pelo SetLocale —
| por isso os route('buy') espalhados pelo projeto continuam a funcionar sem
| saber de idiomas e geram sempre o endereço do idioma que está a ser servido.
|
| Rotas separadas para comprar e arrendar (não um índice único com filtro).
| Os nomes das rotas são estáveis; os slugs em português são a face pública.
| Filtros das listagens vivem na query string (Livewire #[Url]).
|
*/

// A raiz manda para o idioma por omissão (com o prefixo da instalação, se houver).
Route::get('/', fn () => redirect()->route('home', ['locale' => Locales::default()]))->name('root');

Route::prefix('{locale}')
    ->where(['locale' => Locales::pattern()])
    ->middleware(SetLocale::class)
    ->group(function (): void {
        Route::get('/', [PageController::class, 'home'])->name('home');

        // Listagens
        Route::get('/comprar', [PageController::class, 'buy'])->name('buy');
        Route::get('/arrendar', [PageController::class, 'rent'])->name('rent');

        // Ficha de imóvel — slug semântico: tipo-concelho-referência
        // withTrashed: uma ficha na reciclagem tem de chegar ao controlador para
        // responder 410 (removida), e não 404 (nunca existiu) — num endereço já
        // indexado, a diferença conta para o Google.
        Route::get('/imoveis/{property:slug}', [PropertyController::class, 'show'])
            ->withTrashed()
            ->name('property.show');

        // Zonas (páginas editoriais por concelho/freguesia)
        Route::get('/zonas', [ZoneController::class, 'index'])->name('zones.index');
        Route::get('/zonas/{city}', [ZoneController::class, 'city'])->name('zones.city')->where('city', '[a-z0-9-]+');
        Route::get('/zonas/{city}/{locality}', [ZoneController::class, 'locality'])->name('zones.locality')->where(['city' => '[a-z0-9-]+', 'locality' => '[a-z0-9-]+']);

        // Favoritos (localStorage; o servidor só renderiza os cartões pedidos)
        Route::get('/favoritos', [FavoritesController::class, 'index'])->name('favorites');

        // Institucionais e legais
        Route::get('/quanto-vale-a-minha-casa', [PageController::class, 'valuation'])->name('valuation');
        Route::get('/a-agencia', [PageController::class, 'about'])->name('about');
        Route::get('/contactos', [PageController::class, 'contact'])->name('contact');
        Route::get('/politica-de-privacidade', [PageController::class, 'privacy'])->name('privacy');
        Route::get('/termos-e-condicoes', [PageController::class, 'terms'])->name('terms');
        Route::get('/politica-de-cookies', [PageController::class, 'cookies'])->name('cookies');

        // Leads — POST único para os três formulários; rate limiting em AppServiceProvider (limiter "leads").
        Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads')->name('leads.store');
    });

// SEO — sem idioma: o sitemap lista todos, o robots é um só.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
