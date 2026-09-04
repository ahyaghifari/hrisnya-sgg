<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
 * Panel admin Rekrutmen — hasil repurpose panel `candidate` (dulu jalur pelamar daftar
 * langsung ke HRIS, nonaktif sejak 2026-08-28). Sekarang: admin/pipeline rekrutmen yang
 * dipindah dari project `rekrutmen` (shared DB, koneksi `recruitment`). Auth guard `web`
 * (staff HRIS), gerbang permission SISTEM_REKRUTMEN — lihat User::canAccessPanel().
 * Sisi pelamar TETAP di project `rekrutmen`.
 *
 * Tema/warna/font/nav DISAMAKAN dengan panel `admin` HRIS (viteTheme + palette + sidebar).
 */
class RekrutmenPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('rekrutmen')
            ->path('rekrutmen')
            ->brandName('Rekrutmen — Syifa Global Group')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'gray' => Color::Gray,
                'primary' => [
                    50 => '#f0f4f8', 100 => '#d9e5f0', 200 => '#b3cce0', 300 => '#8db3d1',
                    400 => '#6799c1', 500 => '#042c6c', 600 => '#042c6c', 700 => '#032356',
                    800 => '#021a40', 900 => '#01122b', 950 => '#000915',
                ],
                'success' => [
                    50 => '#f0fdf4', 100 => '#dcfce7', 200 => '#bbf7d0', 300 => '#86efac',
                    400 => '#4ade80', 500 => '#047C54', 600 => '#047C54', 700 => '#036143',
                    800 => '#024732', 900 => '#012e21', 950 => '#001710',
                ],
            ])
            ->navigationGroups([
                'Lowongan',
                'Kandidat',
                'Master',
            ])
            ->globalSearch(false)
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
