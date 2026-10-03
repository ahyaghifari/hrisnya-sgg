<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel /surat — penomoran & arsip surat, DB terpisah (koneksi `persuratan`). Auth guard
 * `web` (staff HRIS). Tampilan sengaja beda dari panel HRIS: biru muda kalem, beranda simpel.
 * Akses: sementara Admin/Super Admin (lihat User::canAccessPanel).
 */
class PersuratanPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('persuratan')
            ->path('surat')
            ->brandName('HRIS Nomor Surat')
            // Beda dari HRIS (Plus Jakarta Sans): Source Sans 3, mirip Calibri tapi tersedia di semua OS.
            ->font('Source Sans 3')
            ->colors([
                // Primary = warna menu aktif topbar (#1f6fa8) di shade 600, yang dipakai tombol Filament.
                'primary' => [
                    50 => '#eff7fc', 100 => '#dcecf8', 200 => '#bcd9f0', 300 => '#8ec0e4',
                    400 => '#58a0d3', 500 => '#2a7db8', 600 => '#1f6fa8', 700 => '#185d8f',
                    800 => '#134a73', 900 => '#0f3a5a', 950 => '#0a2540',
                ],
            ])
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.persuratan.background'))
            ->pages([\App\Filament\Persuratan\Pages\Dashboard::class])
            ->topNavigation()
            ->globalSearch(false)
            ->discoverResources(in: app_path('Filament/Persuratan/Resources'), for: 'App\Filament\Persuratan\Resources')
            ->discoverPages(in: app_path('Filament/Persuratan/Pages'), for: 'App\Filament\Persuratan\Pages')
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
