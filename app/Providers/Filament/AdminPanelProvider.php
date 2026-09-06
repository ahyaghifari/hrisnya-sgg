<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Guava\Calendar\CalendarPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn () => Auth::user()?->company?->name ?? 'Syifa Global Group')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'gray' => Color::Gray,
                'primary' => [
                    50 => '#f0f4f8',
                    100 => '#d9e5f0', 
                    200 => '#b3cce0',
                    300 => '#8db3d1',
                    400 => '#6799c1',
                    500 => '#042c6c',
                    600 => '#042c6c',
                    700 => '#032356',
                    800 => '#021a40',
                    900 => '#01122b',
                    950 => '#000915',
                ],
                'success' => [
                    50 => '#f0fdf4',
                    100 => '#dcfce7',
                    200 => '#bbf7d0',
                    300 => '#86efac',
                    400 => '#4ade80',
                    500 => '#047C54',
                    600 => '#047C54',
                    700 => '#036143',
                    800 => '#024732',
                    900 => '#012e21',
                    950 => '#001710',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->globalSearch(false)
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Master Perusahaan')->collapsed(),
                NavigationGroup::make()->label('Manajemen Pengguna'),
                NavigationGroup::make()->label('Kepegawaian')->collapsed(),
                NavigationGroup::make()->label('Sikartap')->collapsed(),
                NavigationGroup::make()->label('Kontrak & Orientasi')->collapsed(),
                NavigationGroup::make()->label('Rekrutmen')->collapsed(),
                NavigationGroup::make()->label('Presensi & Absensi'),
                NavigationGroup::make()->label('Penilaian Karyawan')->collapsed(),
                NavigationGroup::make()->label('Manajemen Organisasi'),
                NavigationGroup::make()->label('Keuangan')->collapsed(),
                NavigationGroup::make()->label('LMS')->collapsed(),
                NavigationGroup::make()->label('Master')->collapsed(),
            ])
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
            ])
            ->plugins([
                CalendarPlugin::make(),
            ]);
    }
}
