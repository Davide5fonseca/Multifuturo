<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Calendario;
use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Widgets\BuyerLeadsWidget;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\ListingLeadsWidget;
use App\Filament\Widgets\PropertyActivitiesWidget;
use App\Filament\Widgets\PropertyViewsChart;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Http\Middleware\EnsureAccountActive;
use Filament\Actions\Action;
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
 *
 * É o módulo "backoffice" do portal (config/modules.php): não tem login próprio
 * — entra-se por /entrar e escolhe-se o módulo em /portal. Quem não tiver
 * acesso ao módulo recebe 403 (User::canAccessPanel). Sem registo público:
 * os utilizadores são criados por um administrador.
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
            // Sem ->login(): a entrada é a do portal. Quem chegar aqui sem sessão
            // vai parar a /entrar (redirectGuestsTo, bootstrap/app.php).
            // Cada pessoa muda o seu nome e a sua palavra-passe sem depender de
            // ninguém; e quem se esquecer dela recupera-a por email.
            ->profile(isSimple: false)
            ->passwordReset()
            // Voltar à página de escolha dos módulos a partir do menu da pessoa.
            ->userMenuItems([
                Action::make('portal')
                    ->label('Portal')
                    ->icon('heroicon-o-squares-2x2')
                    ->url(fn (): string => route('portal')),
            ])
            ->brandName('Multifuturo Propriedades')
            ->colors([
                // Escala azeitona definida à mão: o gerador automático do Filament
                // produzia tons fluorescentes a partir do nosso hex. O tom 600 é o
                // verde exacto do logótipo oficial (#5D6348).
                'primary' => [
                    50 => '#F4F5EF',
                    100 => '#E6E8DC',
                    200 => '#CFD3BC',
                    300 => '#AFB596',
                    400 => '#87906C',
                    500 => '#6C734F',
                    600 => '#5D6348',
                    700 => '#4A4F39',
                    800 => '#393D2B',
                    900 => '#282C1E',
                    950 => '#171911',
                ],
            ])
            ->brandLogo(asset('images/marca/simbolo.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/marca/favicon-192.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                Calendario::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Os números primeiro; depois o gráfico e os quadros do antigo CRM.
                DashboardStats::class,
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
                EnsureAccountActive::class,
            ]);
    }
}
