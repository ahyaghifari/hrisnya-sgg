<?php

use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Livewire\Coaching\MyCoachingPage;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use App\Livewire\MeritPoint\MeritPointHistoryPage;
use App\Livewire\Pembinaan\PembinaanSettingsPage;
use App\Livewire\Sp\CreateSpPage;
use App\Livewire\Sp\SpDetailPage;
use App\Livewire\Sp\SpPage;
use App\Livewire\Sp\SpRecommendationsPage;
use App\Livewire\Sp\SuratPeringatanDetailPage;
use App\Livewire\Sp\SuratPeringatanPage;
use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Sp\EmployeeSp;
use App\Services\Sp\SpPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Pembinaan Karyawan (eks-Coaching & Counseling). Dipisah dari routes/web.php
 * (2026-09-04) - lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 */
Route::get('/coaching', MyCoachingPage::class)
    ->name('coaching.saya');

Route::get('/counseling', CounselingPage::class)
    ->name('counseling');

// SP - route baru, sengaja tidak ditumpuk ke /coaching (planning/2026-09-23-sp-dan-merit-point.md
// §2.1: SP itu legal/formal, harus kelihatan sebagai bagian tersendiri di navigasi).
Route::get('/surat-peringatan', SuratPeringatanPage::class)
    ->name('sp.saya');

Route::get('/surat-peringatan/{record}', SuratPeringatanDetailPage::class)
    ->name('sp.saya.show');

// Preview PDF (bukan download) - GET biasa target=_blank, BUKAN aksi Livewire (Livewire selalu
// memaksa browser save-as apa pun response-nya). Pola persis routes/keuangan.php +
// routes/portal-akun.php (preview slip gaji). Gerbang sama dengan SuratPeringatanDetailPage::mount().
Route::get('/surat-peringatan/{record}/preview', function (EmployeeSp $record) {
    abort_unless($record->user_id === Auth::id(), 403);

    return app(SpPdfService::class)->preview($record);
})->name('sp.saya.preview');

// Merit Point masih DEMO (§3) - dibuka lewat ikon "riwayat" di widget tab Karir, bukan lewat nav utama.
Route::get('/merit-point/riwayat', MeritPointHistoryPage::class)
    ->name('merit-point.saya.riwayat');

// Sisi konselor/atasan (row 2 nav, "Coaching & Counseling") — beda prefix dari sisi
// personal (/coaching, /counseling) di atas, biar ketahuan dari URL-nya ini bukan
// halaman self-service.
Route::prefix('pembinaan')->group(function () {
    Route::get('/coaching', CoachingPage::class)
        ->name('coaching');

    Route::get('/coaching/tambah', CreateCoachingPage::class)
        ->name('coaching.create');

    Route::get('/coaching/{record}', CoachingDetailPage::class)
        ->name('coaching.show');

    Route::get('/counseling', CounselingManagePage::class)
        ->name('counseling.kelola');

    Route::get('/counseling/{record}', CounselingDetailPage::class)
        ->name('counseling.kelola.show');

    // Portal SP sisi HC (planning/2026-09-23-sp-dan-merit-point.md, konfirmasi user 2026-09-23:
    // dibangun paralel sama Filament EmployeeSpResource/SpRecommendationResource, pola persis
    // Coaching di atas - bukan pengganti, keduanya tetap ada).
    Route::get('/sp', SpPage::class)
        ->name('sp');

    Route::get('/sp/tambah', CreateSpPage::class)
        ->name('sp.create');

    Route::get('/sp/{record}', SpDetailPage::class)
        ->name('sp.show');

    // Preview PDF sisi HC - gerbang sama dengan SpDetailPage::mount().
    Route::get('/sp/{record}/preview', function (EmployeeSp $record) {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('View:EmployeeSp'), 403);
        abort_unless($record->company_id === $user->company_id || PermissionHelper::hasGlobalRole($user), 403);

        return app(SpPdfService::class)->preview($record);
    })->name('sp.preview');

    Route::get('/sp-rekomendasi', SpRecommendationsPage::class)
        ->name('sp.rekomendasi');

    Route::get('/pengaturan', PembinaanSettingsPage::class)
        ->name('pembinaan.pengaturan');
});
