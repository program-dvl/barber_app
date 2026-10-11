<?php

namespace App\Providers\Filament;

use App\Filament\Auth\AdminEmailVerificationPrompt;
use App\Filament\Auth\AdminLogin;
use App\Filament\Widgets\AdminOverview;
use App\Filament\Widgets\UsersChart;
use App\Http\Middleware\EnsureAdminSecurity;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(AdminLogin::class)
            ->emailVerification(AdminEmailVerificationPrompt::class)
            ->authenticatedRoutes(function (): void {
                Route::redirect('/multi-factor-authentication/set-up', '/admin')
                    ->name('auth.retired-multi-factor-setup');
            })
            ->brandName(config('brand.product_name').' administration')
            ->favicon(config('brand.favicon'))
            ->font('Manrope', url: asset('fonts/clipperdesk/manrope.css'), provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::hex(config('brand.colors.action_primary')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([AdminOverview::class, UsersChart::class])
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
            ->viteTheme('resources/css/filament/app/theme.css')
            ->authMiddleware([
                Authenticate::class,
                EnsureAdminSecurity::class,
            ], isPersistent: true)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Payments')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make()
                    ->label('Content')
                    ->icon('heroicon-o-document-text'),
                NavigationGroup::make()
                    ->label('Users')
                    ->icon('heroicon-o-users'),

            ])
            ->sidebarCollapsibleOnDesktop();
    }
}
