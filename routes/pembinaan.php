<?php

use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Pembinaan\PembinaanSayaPage;
use App\Livewire\Pembinaan\PembinaanSettingsPage;
use App\Livewire\Sp\BeritaAcaraDetailPage;
use App\Livewire\Sp\BeritaAcaraSayaPage;
use App\Livewire\Sp\CreateBeritaAcaraPage;
use App\Livewire\Sp\SpDetailPage;
use App\Livewire\Sp\SpPage;
use App\Livewire\Sp\SpRecommendationsPage;
use App\Livewire\Sp\SuratPeringatanDetailPage;
use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportStatus;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Pembinaan Karyawan (eks-Coaching & Counseling). Dipisah dari routes/web.php
 * (2026-09-04) - lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 */
// Coaching/Counseling/SP milik sendiri: SATU halaman "Pembinaan Saya" (menu terpisah). Tab Pembinaan di
// Profil cuma untuk melihat profil ORANG LAIN. URL lama diarahkan ke sini supaya bookmark tidak 404.
Route::get('/pembinaan-saya', PembinaanSayaPage::class)
    ->name('pembinaan.saya');
Route::redirect('/coaching', '/pembinaan-saya');
Route::redirect('/counseling', '/pembinaan-saya');
Route::redirect('/surat-peringatan', '/pembinaan-saya');

Route::get('/surat-peringatan/{record}', SuratPeringatanDetailPage::class)
    ->name('sp.saya.show');

// Undangan (baca saja) untuk yang diundang & pembuatnya - tujuan klik notifikasi undangan.
Route::get('/undangan/{record}', \App\Livewire\InvitationViewPage::class)
    ->name('undangan.show');

// Berita Acara milik karyawan sendiri (alur baru, instruksi user 2026-10-01).
Route::get('/berita-acara/{record}', BeritaAcaraSayaPage::class)
    ->name('sp.saya.ba');

// PDF Berita Acara mandiri: baru bisa dilihat setelah SELESAI (3 pihak sudah TTD).
Route::get('/berita-acara/{record}/preview', function (SpReport $record) {
    abort_unless($record->user_id === Auth::id() && ! $record->isLegacy() && $record->status === SpReportStatus::SELESAI, 403);

    return app(SpPdfService::class)->previewReport($record);
})->name('sp.saya.ba.preview');

// Preview PDF (bukan download) - GET biasa target=_blank, BUKAN aksi Livewire (Livewire selalu
// memaksa browser save-as apa pun response-nya). Pola persis routes/keuangan.php +
// routes/portal-akun.php (preview slip gaji). Gerbang sama dengan SuratPeringatanDetailPage::mount().
Route::get('/surat-peringatan/{record}/preview', function (EmployeeSp $record) {
    abort_unless($record->user_id === Auth::id() && $record->isIssued(), 403);

    return app(SpPdfService::class)->preview($record);
})->name('sp.saya.preview');

// Preview PDF (bukan download) - GET biasa target=_blank, BUKAN aksi Livewire (Livewire selalu
// memaksa browser save-as apa pun response-nya). Pola persis routes/keuangan.php +
Route::get('/surat-peringatan/{record}/berita-acara-preview', function (EmployeeSp $record) {
    abort_unless($record->user_id === Auth::id() && $record->isIssued(), 403);

    return app(SpPdfService::class)->previewBeritaAcara($record);
})->name('sp.saya.beritaacara_preview');


// Sisi konselor/atasan (row 2 nav, "Coaching & Counseling") — beda prefix dari sisi
// personal (/coaching, /counseling) di atas, biar ketahuan dari URL-nya ini bukan
// halaman self-service.
Route::prefix('pembinaan')->group(function () {
    Route::get('/coaching', CoachingPage::class)
        ->name('coaching');

    Route::get('/coaching/tambah', CreateCoachingPage::class)
        ->name('coaching.create');

    // Undangan coaching (tabel invitations, type COACHING) - dibuat coach sebelum sesi.
    Route::get('/coaching/undangan/tambah', \App\Livewire\Coaching\CoachingInvitationPage::class)
        ->name('coaching.undangan.create');

    Route::get('/coaching/undangan/{record}', \App\Livewire\Coaching\CoachingInvitationPage::class)
        ->name('coaching.undangan.show');

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
    // 2026-10-01); jalur langsung tanpa BA sudah ditutup. Form SP tertanam sebagai langkah 2 di
    // halaman detail BA (ba.show), bukan halaman/menu terpisah (instruksi user 2026-10-02).

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

    // Undangan klarifikasi: tahap 1, wajib sebelum Berita Acara.
    Route::get('/sp-undangan/tambah', \App\Livewire\Sp\SpInvitationPage::class)
        ->name('sp.undangan.create');

    Route::get('/sp-undangan/{record}', \App\Livewire\Sp\SpInvitationPage::class)
        ->name('sp.undangan.show');

    // Berita Acara alur baru (instruksi user 2026-10-01) - proses klarifikasi sebelum SP.
    Route::get('/berita-acara/tambah', CreateBeritaAcaraPage::class)
        ->name('ba.create');

    Route::get('/berita-acara/{record}', BeritaAcaraDetailPage::class)
        ->name('ba.show');

    // Gerbang sama dengan BeritaAcaraDetailPage::mount(). Sebelum SELESAI (draft / menunggu TTD)
    // yang tampil hanya pratinjau berwatermark, bukan dokumen final.
    Route::get('/berita-acara/{record}/preview', function (SpReport $record) {
        $user = Auth::user();

        abort_unless(
            ! $record->isLegacy()
                && ($user->id === $record->created_by
                    || $user->id === $record->knows_by
                    || $user->id === $record->supervisor_id
                    || ($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp') && $user->company_id === $record->company_id)),
            403
        );

        return app(SpPdfService::class)->previewReport($record);
    })->name('ba.preview');

    Route::get('/sp-rekomendasi', SpRecommendationsPage::class)
        ->name('sp.rekomendasi');

    Route::get('/pengaturan', PembinaanSettingsPage::class)
        ->name('pembinaan.pengaturan');

    Route::get('/pengaturan/pelanggaran', \App\Livewire\Pembinaan\SpReasonsPage::class)
        ->name('pembinaan.pelanggaran');
});
