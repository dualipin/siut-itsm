<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Models\Theme;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use function asset;
use function config;

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $theme = null;
        try {
            $theme = Theme::where('id', 1)->first();
        } catch (\Throwable $e) {
            // Database/table may not exist yet during testing or migrations

        }

        return $panel
            ->default()
            ->id('portal')
            ->path('portal')
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->profile(Profile::class, isSimple: false)
            ->navigationItems([
                NavigationItem::make('Mi Perfil')
                    ->url(fn (): string => route('filament.portal.auth.profile'))
                    ->icon(Heroicon::UserCircle)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.portal.auth.profile'))
                    ->sort(100),
            ])
            ->colors([
                'primary' => $theme?->getVariants($theme?->color_primary) ?? Color::Red,
                'success' => $theme?->color_success ?? Color::Emerald,
                'warning' => $theme?->color_warning ?? Color::Orange,
                'danger' => $theme?->color_error ?? Color::Red,
                'info' => $theme?->color_info ?? Color::Blue,
                'gray' => $theme?->color_neutral ?? Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                AuthUIEnhancerPlugin::make()
                    ->mobileFormPanelPosition('bottom')
                    ->emptyPanelBackgroundImageOpacity('80%')
                    ->formPanelWidth('40%')
                    ->emptyPanelBackgroundImageUrl(
                        asset('assets/images/login-background.jpg')
                    )
            )
            ->brandLogo(fn () => view('components.icon-admin'))
            ->brandLogoHeight('3.5rem')
            ->brandName(config('app.name'))
            ->favicon(asset('favicon.ico'));
    }
}
