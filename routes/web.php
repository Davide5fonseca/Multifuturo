<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\MfaController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\TeamController;
use App\Http\Controllers\PropertyCardsController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchSuggestController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ZoneController;
use App\Http\Middleware\EnsureAccountActive;
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

/*
|--------------------------------------------------------------------------
| Portal da equipa — entrada única e escolha de módulo
|--------------------------------------------------------------------------
|
| Fora do prefixo de idioma: é uma área interna, sempre em português. A
| sessão começa em /entrar (e, com a verificação em duas etapas, só depois
| do código em /verificar); depois aterra-se em /portal, onde se escolhe o
| módulo. O backoffice (/admin) é o primeiro módulo e já não tem login
| próprio.
|
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    Route::get('/verificar', [MfaController::class, 'show'])->name('mfa.show');
    Route::post('/verificar', [MfaController::class, 'verify'])->middleware('throttle:20,1')->name('mfa.verify');
    Route::post('/verificar/reenviar', [MfaController::class, 'resend'])->middleware('throttle:6,1')->name('mfa.resend');
});

Route::middleware(['auth', EnsureAccountActive::class])->group(function (): void {
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');

    // Gestão — só administradores. A equipa e os acessos vivem aqui, não no backoffice.
    Route::middleware('can:admin')->prefix('gestao')->name('team.')->group(function (): void {
        Route::get('/equipa', [TeamController::class, 'index'])->name('index');
        Route::get('/equipa/nova', [TeamController::class, 'create'])->name('create');
        Route::post('/equipa', [TeamController::class, 'store'])->name('store');
        Route::get('/equipa/{user}', [TeamController::class, 'edit'])->name('edit');
        Route::put('/equipa/{user}', [TeamController::class, 'update'])->name('update');
        Route::delete('/equipa/{user}', [TeamController::class, 'destroy'])->name('destroy');
    });
});

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

        // Comparador (até 3 imóveis; a escolha vive no browser, como os favoritos)
        Route::get('/comparar', CompareController::class)->name('compare');

        // Sugestões da pesquisa (concelhos, freguesias, imóveis) enquanto se escreve,
        // e o fragmento de cartões pedido pelos "vistos recentemente". Só leitura.
        Route::get('/pesquisa/sugestoes', SearchSuggestController::class)->middleware('throttle:60,1')->name('search.suggest');
        Route::get('/imoveis-cartoes', PropertyCardsController::class)->middleware('throttle:60,1')->name('property.cards');

        // Institucionais e legais
        Route::get('/quanto-vale-a-minha-casa', [PageController::class, 'valuation'])->name('valuation');
        Route::get('/a-agencia', [PageController::class, 'about'])->name('about');
        Route::get('/contactos', [PageController::class, 'contact'])->name('contact');
        Route::get('/politica-de-privacidade', [PageController::class, 'privacy'])->name('privacy');
        Route::get('/termos-e-condicoes', [PageController::class, 'terms'])->name('terms');
        Route::get('/politica-de-cookies', [PageController::class, 'cookies'])->name('cookies');

        // Leads — POST único para os três formulários; rate limiting em AppServiceProvider (limiter "leads").
        Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads')->name('leads.store');

        // Registo das escolhas do aviso de cookies (prova do consentimento, RGPD art. 7.º).
        Route::post('/consentimento', [ConsentController::class, 'store'])->middleware('throttle:leads')->name('consent.store');
    });

// SEO — sem idioma: o sitemap lista todos, o robots é um só.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
