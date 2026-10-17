<?php

use App\Livewire\Rekrutmen\Mcu\PemeriksaanDokterPage as McuDokterPage;
use App\Livewire\Rekrutmen\Mcu\QueuePage as McuQueuePage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Rekrutmen. Pipeline pelamar & Manajemen Lowongan Kerja sepenuhnya panel
 * Filament sekarang ("rencananya memang full panel", instruksi user 2026-09-04) - Livewire
 * portal lama sudah dihapus, redirect shim-nya ikut dicabut di sini (2026-09-04), tidak ada
 * lagi bookmark lama yang perlu dijaga.
 *
 * Yang tersisa di sini cuma route file-serving murni (proxy/baca disk) - panel Filament
 * render HTML, bukan stream file, jadi ini TETAP harus route Laravel biasa, bukan bagian
 * yang bisa dipindah ke panel.
 *
 * Dipisah dari routes/web.php (2026-09-04) - lihat komentar di routes/sikartap.php untuk
 * konvensi split per-modul.
 */

// {jobApplication} = ULID milik project Rekrutmen eksternal (bukan model HRIS) - string biasa.
Route::get('/rekrutmen/pelamar/{jobApplication}/dokumen/{document}', [\App\Http\Controllers\Rekrutmen\CandidateDocumentProxyController::class, 'show'])
    ->name('rekrutmen.pelamar.dokumen');

Route::get('/rekrutmen/pelamar/{jobApplication}/offering-letter/{type}', [\App\Http\Controllers\Rekrutmen\CandidateDocumentProxyController::class, 'offeringLetter'])
    ->where('type', 'recruiter|signed')
    ->name('rekrutmen.pelamar.offering-letter');

// Panel Filament rekrutmen (shared DB) — lihat surat offering letter. `signed` selalu file
// upload kandidat dari disk `rekrutmen`. `recruiter` = file upload manual recruiter kalau ada
// (override); kalau tidak ada, di-render LANGSUNG dari data kandidat + config perusahaan/
// lowongan saat ini (JobApplication::renderOfferingLetterPdf(), tidak disimpan) — selalu versi
// terbaru, tidak ada tombol "generate" terpisah. Gate SISTEM_REKRUTMEN.
Route::get('/rekrutmen-file/offering-letter/{jobApplication}/{type}', function (string $jobApplication, string $type) {
    abort_unless(in_array($type, ['recruiter', 'signed'], true), 404);
    abort_unless(\Illuminate\Support\Facades\Auth::user()?->can(\App\Enum\CustomPermissionEnum::SISTEM_REKRUTMEN->value), 403);

    $app = \App\Models\Rekrutmen\JobApplication::with('jobOpening.company')->find($jobApplication);

    if ($type === 'signed') {
        $path = $app?->offering_letter_signed_path;
        abort_if(! $path || ! \Illuminate\Support\Facades\Storage::disk('rekrutmen')->exists($path), 404, 'File tidak ada.');

        return response(\Illuminate\Support\Facades\Storage::disk('rekrutmen')->get($path), 200, [
            'Content-Type' => \Illuminate\Support\Facades\Storage::disk('rekrutmen')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="offering-signed-' . $jobApplication . '"',
        ]);
    }

    if ($app?->offering_letter_path && \Illuminate\Support\Facades\Storage::disk('rekrutmen')->exists($app->offering_letter_path)) {
        return response(\Illuminate\Support\Facades\Storage::disk('rekrutmen')->get($app->offering_letter_path), 200, [
            'Content-Type' => \Illuminate\Support\Facades\Storage::disk('rekrutmen')->mimeType($app->offering_letter_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="offering-recruiter-' . $jobApplication . '"',
        ]);
    }

    abort_if(! $app?->offering_data_confirmed_at, 404, 'File tidak ada.');

    return $app->renderOfferingLetterPdf()->stream('offering-letter.pdf');
})->name('rekrutmen.file.offering-letter');

// Perjanjian Kerja Mitra — PDF dirender langsung saat onboarding (tombol di TTD & Onboarding).
Route::get('/rekrutmen-file/perjanjian-mitra/{jobApplication}', function (string $jobApplication) {
    abort_unless(\Illuminate\Support\Facades\Auth::user()?->can(\App\Enum\CustomPermissionEnum::SISTEM_REKRUTMEN->value), 403);

    $app = \App\Models\Rekrutmen\JobApplication::with(['jobOpening.company', 'candidate', 'user'])->findOrFail($jobApplication);
    abort_if(! $app->offering_data_confirmed_at, 404, 'Data offering kandidat belum dikonfirmasi.');

    return $app->renderMitraAgreementPdf()->stream('perjanjian-mitra.pdf');
})->name('rekrutmen.file.perjanjian-mitra');

/**
 * Modul MCU rekrutmen — pindah dari panel Filament (McuStationPage dihapus 2026-09-08) ke
 * portal supaya staf klinis tidak perlu akses panel /rekrutmen. Gate: canAccessMcuModule()
 * (antrian) + INPUT_MCU_* per stasiun, lihat config/portal-pages.php.
 */
// Perawat, lab & radiologi tidak punya halaman sendiri lagi (2026-09-30) - isiannya cuma unggah
// satu berkas, jadi dikerjakan lewat modal di antrian. Dokter tetap punya halaman karena dia perlu
// membaca berkas ketiga stasiun itu dulu sebelum memutuskan fit/tidak fit.
Route::get('/mcu/rekrutmen', McuQueuePage::class)->name('rekrutmen.mcu.queue');
Route::get('/mcu/rekrutmen/{invitee}/dokter', McuDokterPage::class)->name('rekrutmen.mcu.dokter');
