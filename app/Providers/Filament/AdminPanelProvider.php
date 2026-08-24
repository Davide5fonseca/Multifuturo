<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Calendario;
use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Widgets\BuyerLeadsWidget;
use App\Filament\Widgets\ListingLeadsWidget;
use App\Filament\Widgets\PropertyActivitiesWidget;
use App\Filament\Widgets\PropertyViewsChart;
use App\Filament\Widgets\UpcomingEventsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Backoffice da Multifuturo (/admin) — substitui o CRM: é aqui que a equipa
 * gere imóveis, consulta as leads do site e edita o conteúdo das zonas.
 * Sem registo público: os utilizadores são criados por um administrador.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Como no CRM: a barra lateral abre e fecha, e o sino das
            // notificações avisa dos novos pedidos do site (verifica de 30 em 30 s).
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->login()
            ->brandName('Multifuturo.')
            ->colors([
                // Escala azeitona definida à mão: o gerador automático do Filament
                // produzia tons fluorescentes a partir do nosso hex. Os tons 600/700
                // são exatamente os da marca (olive-600 / olive-700).
                'primary' => [
                    50 => '#F5F6F0',
                    100 => '#E8EADE',
                    200 => '#D2D6BF',
                    300 => '#B4BB99',
                    400 => '#8F9970',
                    500 => '#77804F',
                    600 => '#6B7248',
                    700 => '#565C39',
                    800 => '#43482D',
                    900 => '#2F3320',
                    950 => '#1B1D12',
                ],
            ])
            ->favicon(asset('favicon-192.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                Calendario::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Dashboard com os mesmos quadros do antigo CRM.
                ListingLeadsWidget::class,
                BuyerLeadsWidget::class,
                PropertyActivitiesWidget::class,
                UpcomingEventsWidget::class,
                PropertyViewsChart::class,
            ])
            // Leaflet só nas páginas com mapa (ficha do imóvel), não em todo o painel.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.leaflet-assets'),
                scopes: [CreateProperty::class, EditProperty::class],
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
