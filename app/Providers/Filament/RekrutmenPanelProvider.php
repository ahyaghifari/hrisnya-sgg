<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel admin Rekrutmen — hasil repurpose panel `candidate` (dulu jalur pelamar daftar
 * langsung ke HRIS, nonaktif sejak 2026-08-28). Sekarang: admin/pipeline rekrutmen yang
 * dipindah dari project `rekrutmen` (shared DB, koneksi `recruitment`). Auth guard `web`
 * (staff HRIS), gerbang permission SISTEM_REKRUTMEN — lihat User::canAccessPanel().
 * Sisi pelamar TETAP di project `rekrutmen`.
 */
class RekrutmenPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('rekrutmen')
            // /kelola-rekrutmen (sama seperti panel asal di project `rekrutmen`) — bukan
            // /rekrutmen supaya tidak bentrok route Livewire `rekrutmen.*` yang masih hidup.
            // Nanti setelah port lengkap, route Livewire itu diarahkan ke sini.
            ->path('kelola-rekrutmen')
            ->brandName('Rekrutmen — Syifa Global Group')
            ->defaultThemeMode(ThemeMode::Light)
            ->darkMode(false)
            ->topNavigation()
            ->navigationGroups([
                'Lowongan',
                'Kandidat',
                'Master',
            ])
            ->colors([
                'primary' => [
                    50 => '#f0f4f8', 100 => '#d9e5f0', 200 => '#b3cce0', 300 => '#8db3d1',
                    400 => '#6799c1', 500 => '#042c6c', 600 => '#042c6c', 700 => '#032356',
                    800 => '#021a40', 900 => '#01122b', 950 => '#000915',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Rekrutmen/Resources'), for: 'App\Filament\Rekrutmen\Resources')
            ->discoverPages(in: app_path('Filament/Rekrutmen/Pages'), for: 'App\Filament\Rekrutmen\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Rekrutmen/Widgets'), for: 'App\Filament\Rekrutmen\Widgets')
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
