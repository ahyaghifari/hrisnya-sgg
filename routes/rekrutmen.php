<?php

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

// Panel Filament rekrutmen (shared DB) — unduh file offering letter LANGSUNG dari disk
// `rekrutmen` (REKRUTMEN_STORAGE_PATH), bukan proxy API. Gate SISTEM_REKRUTMEN.
Route::get('/rekrutmen-file/offering-letter/{jobApplication}/{type}', function (string $jobApplication, string $type) {
    abort_unless(in_array($type, ['recruiter', 'signed'], true), 404);
    abort_unless(\Illuminate\Support\Facades\Auth::user()?->can(\App\Enum\CustomPermissionEnum::SISTEM_REKRUTMEN->value), 403);

    $app = \App\Models\Rekrutmen\JobApplication::find($jobApplication);
    $path = $type === 'recruiter' ? $app?->offering_letter_path : $app?->offering_letter_signed_path;
    abort_if(! $path || ! \Illuminate\Support\Facades\Storage::disk('rekrutmen')->exists($path), 404, 'File tidak ada.');

    return response(
        \Illuminate\Support\Facades\Storage::disk('rekrutmen')->get($path),
        200,
        [
            'Content-Type' => \Illuminate\Support\Facades\Storage::disk('rekrutmen')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="offering-' . $type . '-' . $jobApplication . '"',
        ],
    );
})->name('rekrutmen.file.offering-letter');
