<?php

use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Livewire\Coaching\MyCoachingPage;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use App\Livewire\Pembinaan\PembinaanSettingsPage;
use App\Livewire\Sp\BeritaAcaraDetailPage;
use App\Livewire\Sp\BeritaAcaraPage;
use App\Livewire\Sp\BeritaAcaraSayaPage;
use App\Livewire\Sp\CreateBeritaAcaraPage;
use App\Livewire\Sp\CreateSpFromReportPage;
use App\Livewire\Sp\SpDetailPage;
use App\Livewire\Sp\SpPage;
use App\Livewire\Sp\SpRecommendationsPage;
use App\Livewire\Sp\SuratPeringatanDetailPage;
use App\Livewire\Sp\SuratPeringatanPage;
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

// Berita Acara milik karyawan sendiri (alur baru, instruksi user 2026-10-01).
Route::get('/berita-acara/{record}', BeritaAcaraSayaPage::class)
    ->name('sp.saya.ba');

// Preview PDF (bukan download) - GET biasa target=_blank, BUKAN aksi Livewire (Livewire selalu
// memaksa browser save-as apa pun response-nya). Pola persis routes/keuangan.php +
// routes/portal-akun.php (preview slip gaji). Gerbang sama dengan SuratPeringatanDetailPage::mount().
Route::get('/surat-peringatan/{record}/preview', function (EmployeeSp $record) {
    abort_unless($record->user_id === Auth::id(), 403);

    return app(SpPdfService::class)->preview($record);
})->name('sp.saya.preview');

// Preview PDF (bukan download) - GET biasa target=_blank, BUKAN aksi Livewire (Livewire selalu
// memaksa browser save-as apa pun response-nya). Pola persis routes/keuangan.php +
Route::get('/surat-peringatan/{record}/berita-acara-preview', function (EmployeeSp $record) {
    abort_unless($record->user_id === Auth::id(), 403);

    return app(SpPdfService::class)->previewBeritaAcara($record);
})->name('sp.saya.beritaacara_preview');


// Merit Point masih DEMO (§3) - riwayat & transaksi dibuka lewat panel di widget tab Karir
// (Livewire EmployeeStatistics), BUKAN halaman/route terpisah (instruksi user
// 2026-09-25). Route lama merit-point.saya.riwayat dihapus, lihat getMeritHistoryProperty().

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

    // SP hanya dibuat dari Berita Acara yang selesai & diputuskan lanjut SP (instruksi user
    // 2026-10-01); jalur langsung tanpa BA (CreateSpPage & pembuatan di panel admin) sudah ditutup.
    Route::get('/sp/tambah', CreateSpFromReportPage::class)
        ->name('sp.create');

    Route::get('/sp/{record}', SpDetailPage::class)
        ->name('sp.show');

    // Preview PDF - gerbang sama dengan SpDetailPage::mount() (HC ATAU "Mengetahui" yang
    // ditunjuk di SP ini, lihat EmployeeSp::canBeViewedBy(), instruksi user 2026-09-28).
    Route::get('/sp/{record}/preview', function (EmployeeSp $record) {
        abort_unless($record->canBeViewedBy(Auth::user()), 403);

        return app(SpPdfService::class)->preview($record);
    })->name('sp.preview');

    Route::get('/sp/{record}/beritaacara-preview', function (EmployeeSp $record) {
        abort_unless($record->canBeViewedBy(Auth::user()), 403);

        return app(SpPdfService::class)->previewBeritaAcara($record);
    })->name('sp.beritaacara_preview');

    // Berita Acara alur baru (instruksi user 2026-10-01) - proses klarifikasi sebelum SP.
    Route::get('/berita-acara', BeritaAcaraPage::class)
        ->name('ba');

    Route::get('/berita-acara/tambah', CreateBeritaAcaraPage::class)
        ->name('ba.create');

    Route::get('/berita-acara/{record}', BeritaAcaraDetailPage::class)
        ->name('ba.show');

    Route::get('/sp-rekomendasi', SpRecommendationsPage::class)
        ->name('sp.rekomendasi');

    Route::get('/pengaturan', PembinaanSettingsPage::class)
        ->name('pembinaan.pengaturan');
});
