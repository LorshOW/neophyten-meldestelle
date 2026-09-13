<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Außendienst - used on a phone, in the field, usually one-handed.
 *
 * Deliberately minimal: only the cases assigned to the signed-in user, and only
 * the fields they are allowed to fill in on site. The restriction is enforced
 * by VorgangPolicy and by the resource's query scope, not by hiding buttons.
 */
class AussendienstPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('aussendienst')
            ->path('aussendienst')
            ->login()
            // Auf dem Handy zählt jeder Pixel.
            ->sidebarCollapsibleOnDesktop()
            ->brandName('Meldestelle · Außendienst')
            ->colors([
                'primary' => Color::hex('#2f6d3e'),
            ])
            ->discoverResources(in: app_path('Filament/Aussendienst/Resources'), for: 'App\Filament\Aussendienst\Resources')
            ->discoverPages(in: app_path('Filament/Aussendienst/Pages'), for: 'App\Filament\Aussendienst\Pages')
            ->discoverWidgets(in: app_path('Filament/Aussendienst/Widgets'), for: 'App\Filament\Aussendienst\Widgets')
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
