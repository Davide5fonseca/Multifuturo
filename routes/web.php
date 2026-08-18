<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
|
| Rotas separadas para comprar e arrendar (não um índice único com filtro).
| Os nomes das rotas são estáveis; os slugs em português são a face pública.
| Listagens, fichas e zonas ganham componentes Livewire na Fase 4.
|
*/

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/comprar', [PageController::class, 'buy'])->name('buy');
Route::get('/arrendar', [PageController::class, 'rent'])->name('rent');

Route::get('/quanto-vale-a-minha-casa', [PageController::class, 'valuation'])->name('valuation');
Route::get('/a-agencia', [PageController::class, 'about'])->name('about');
Route::get('/contactos', [PageController::class, 'contact'])->name('contact');

Route::get('/politica-de-privacidade', [PageController::class, 'privacy'])->name('privacy');
Route::get('/termos-e-condicoes', [PageController::class, 'terms'])->name('terms');
Route::get('/politica-de-cookies', [PageController::class, 'cookies'])->name('cookies');

// SEO — ambos derivados de config('app.url'); não existem ficheiros estáticos em public/.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
