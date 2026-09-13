<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Kobo\KoboApiClient;
use App\Services\Kobo\KoboDiagnostics;
use App\Services\Kobo\KoboSyncService;
use App\Support\Roles;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // These read their credentials from config; without explicit bindings
        // the container would autowire them with their null defaults and every
        // call would look "not configured".
        $this->app->singleton(KoboApiClient::class, fn () => KoboApiClient::fromConfig());
        $this->app->singleton(KoboSyncService::class, fn ($app) => new KoboSyncService($app->make(KoboApiClient::class)));
        $this->app->singleton(KoboDiagnostics::class, fn ($app) => new KoboDiagnostics($app->make(KoboApiClient::class)));
    }

    public function boot(): void
    {
        // Super-Admin bypasses every policy. Returning null (not false) for
        // everyone else leaves the normal checks untouched.
        Gate::before(fn (User $user, string $ability) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null);

        RateLimiter::for('kobo-webhook', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);

        // Leaflet + vorgangMap must be on the layout. Inline scripts next to
        // the map never run when Livewire injects the markup (modals, tabs).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.hooks.leaflet-head'),
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn () => view('filament.hooks.vorgang-map-scripts'),
        );
    }
}
