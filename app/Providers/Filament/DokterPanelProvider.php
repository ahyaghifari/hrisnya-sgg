<?php

namespace App\Providers\Filament;

use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Pages\LamaranDokter;
use App\Models\AuditLog;
use App\Models\Doctor\DoctorDocument;
use App\Models\Rekrutmen\DoctorApplication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel Filament Dokter (`/dokter`) - kelola master dokter di DB `doctor` (koneksi terpisah dari
 * HRIS). Auth guard `web` (staff HRIS), gerbang permission KELOLA_DOKTER - lihat
 * User::canAccessPanel(). Skema dimiliki HRIS: database/migrations/doctor.
 */
class DokterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dokter')
            ->path('dokter')
            ->brandName('Dokter - HRIS')
            ->darkMode(false)
            ->globalSearch(false)
            ->topNavigation()
            ->maxContentWidth(Width::Full)
            ->font('Source Sans 3')
            // Primary (tombol Filament) = teal gelap: satu hue dengan latar, tapi cukup gelap supaya tombol terbaca.
            ->colors(['primary' => [
                50 => '#f0fdfa', 100 => '#ccfbf1', 200 => '#99f6e4', 300 => '#5eead4', 400 => '#2dd4bf',
                500 => '#14b8a6', 600 => '#0f766e', 700 => '#115e59', 800 => '#134e4a', 900 => '#0f3d3a', 950 => '#042f2e',
            ]])
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.dokter.background'))
            ->pages([Beranda::class, LamaranDokter::class])
            // Lihat/unduh dokumen: file terenkripsi di disk privat, didekripsi di memory. Route ini
            // sudah di balik auth panel (canAccessPanel -> Admin/Super Admin/kelola_dokter).
            ->authenticatedRoutes(function () {
                Route::get('/dokumen/{document}', function (Request $request, DoctorDocument $document) {
                    $disk = Storage::disk('local');
                    abort_unless($disk->exists($document->file_path), 404);

                    $download = $request->boolean('download');
                    AuditLog::record('doctor_document', Auth::user()?->company_id, 'doctor_document:'.$document->id,
                        ($download ? 'Unduh' : 'Lihat')." dokumen dokter #{$document->doctor_id}: {$document->file_name}", null, null);

                    return response(Crypt::decryptString($disk->get($document->file_path)), 200, [
                        'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                        'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.addslashes($document->file_name).'"',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                })->name('dokumen.show');

                // Berkas lamaran (disk `rekrutmen`, tidak terenkripsi). Hanya path yang tercatat di lamaran yang boleh dibuka.
                Route::get('/lamaran/{application}/berkas', function (Request $request, DoctorApplication $application) {
                    $path = (string) $request->query('path');
                    $name = $application->allFiles()[$path] ?? null;
                    abort_unless($name && Storage::disk('rekrutmen')->exists($path), 404);

                    return Storage::disk('rekrutmen')->response($path, $name);
                })->name('lamaran.berkas');
            })
            ->discoverResources(in: app_path('Filament/Dokter/Resources'), for: 'App\Filament\Dokter\Resources')
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
