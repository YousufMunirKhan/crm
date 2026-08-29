<?php

namespace App\Providers\Filament;

use App\Modules\Settings\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Back-office panel at /admin.
 *
 * Shares the SPA's identity deliberately: same primary blue, same manifest and
 * service worker, so the installed home-screen app covers both surfaces and a
 * user never sees a seam between the Vue pages and the Filament ones.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            // Client-side navigation, so the panel still feels like an app
            // inside the installed PWA shell.
            ->spa()
            ->colors([
                'primary' => Color::hex('#2563eb'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->brandName(config('app.name', 'Switch & Save CRM'))
            ->brandLogo(fn () => $this->brandingAsset('logo_path'))
            ->favicon($this->brandingAsset('favicon_path') ?? asset('icons/icon-192x192.png'))
            ->brandLogoHeight('2rem')
            ->navigationGroups([
                'CRM',
                'Catalogue',
                'Marketing',
                'People & money',
                'Communications',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Reuse the SPA's manifest and service worker so the panel is
            // installable and runs standalone exactly like the Vue app.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('<x-pwa-head />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<x-pwa-scripts />'),
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

    /**
     * Branding uploaded through Settings, when the table is available.
     *
     * Guarded because panel providers boot during `migrate` and `config:cache`
     * on a database that may not have the settings table yet.
     */
    private function brandingAsset(string $key): ?string
    {
        try {
            $path = Setting::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            return null;
        }

        return $path ? asset('storage/'.ltrim($path, '/')) : null;
    }
}
