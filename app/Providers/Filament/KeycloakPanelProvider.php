<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel Filament khusus Keycloak (`/keycloak`) — kelola user Keycloak lewat Admin REST API
 * (App\Services\Keycloak\KeycloakAdmin, grant client_credentials). Auth guard `web` (staff
 * HRIS), gerbang permission KELOLA_KEYCLOAK — lihat User::canAccessPanel(). Tema disamakan
 * dengan admin console Keycloak: primary biru, sidebar gelap. Terpisah dari SSO login.
 */
class KeycloakPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('keycloak')
            ->path('keycloak')
            ->brandName('Keycloak - HRIS')
            ->darkMode(false)
            ->sidebarWidth('14rem')
            ->globalSearch(false)
            ->colors([
                'primary' => Color::hex('#0066CC'), // PatternFly blue (Keycloak)
                'gray' => Color::Slate,
            ])
            // Sidebar gelap ala Keycloak — panel ini tanpa custom theme, jadi override CSS di sini.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => <<<'HTML'
                <style>
                    .fi-sidebar, .fi-sidebar-header,
                    .fi-topbar > nav.fi-topbar { background-color: #1b1b1d; }
                    .fi-sidebar { border-color: rgba(255,255,255,.08); }
                    .fi-sidebar .fi-sidebar-item-label { color: #e5e7eb; }
                    .fi-sidebar .fi-sidebar-group-label { color: #9ca3af; }
                    .fi-sidebar .fi-sidebar-item-icon { color: #9ca3af; }
                    .fi-sidebar .fi-sidebar-item-btn:hover { background-color: rgba(255,255,255,.06); }
                    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-btn { background-color: rgba(255,255,255,.1); }
                    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-label,
                    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color: #fff; }

                    .fi-topbar > nav.fi-topbar { color: #e5e7eb; border-color: rgba(255,255,255,.08); }
                    .fi-topbar .fi-icon-btn,
                    .fi-topbar .fi-dropdown-trigger,
                    .fi-topbar .fi-topbar-item-label { color: #cbd5e1; }
                    .fi-topbar .fi-icon-btn:hover { background-color: rgba(255,255,255,.06); }
                </style>
                HTML)
            ->discoverPages(in: app_path('Filament/Keycloak/Pages'), for: 'App\Filament\Keycloak\Pages')
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
