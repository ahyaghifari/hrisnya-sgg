<?php

namespace App\Providers\Filament;

use App\Filament\Candidate\Pages\Auth\Login;
use App\Filament\Candidate\Pages\Auth\Register;
use App\Filament\Candidate\Pages\Dashboard as PagesDashboard;
use App\Filament\Candidate\Widgets\FlagingOnBoardingWidget;
use App\Filament\Candidate\Widgets\JobOpeningWidget;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CandidatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('candidate')
            ->path('candidate')
            ->brandName('Syifa Global Group')
            ->defaultThemeMode(ThemeMode::Light)
            ->authGuard('candidate')
            ->authPasswordBroker('candidates')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->globalSearch(false)
            ->darkMode(false)
            ->colors([
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
            ->discoverResources(in: app_path('Filament/Candidate/Resources'), for: 'App\Filament\Candidate\Resources')
            ->discoverPages(in: app_path('Filament/Candidate/Pages'), for: 'App\Filament\Candidate\Pages')
            ->pages([
                PagesDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Candidate/Widgets'), for: 'App\Filament\Candidate\Widgets')
            ->widgets([
                FlagingOnBoardingWidget::class,
                JobOpeningWidget::class,
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
            ]);
    }
}
