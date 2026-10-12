<?php

use App\Livewire\Document\SignDocumentsPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul TTE + QR internal (docs/2026-09-28-tte-qr-internal-fase-1.md) - GENERIK lintas
 * jenis dokumen (SP, Berita Acara, nanti Surat Tugas dst, instruksi user 2026-09-28: "kenapa
 * dibangun erat dengan SP, kan bisa modal, nanti ada juga Surat Tugas dan yang lainnya"). Sengaja
 * file terpisah, BUKAN ditumpuk ke routes/pembinaan.php - halaman ini tidak spesifik SP, siapa
 * pun bisa ditugaskan menandatangani dokumen jenis apa pun di sini.
 *
 * Route publik /verify/{code} (verifikasi tanpa login) ada di routes/web.php, bukan di sini -
 * ini KHUSUS halaman tanda tangan yang butuh login.
 */
Route::get('/dokumen/tandatangani/{document?}', SignDocumentsPage::class)
    ->name('documents.sign');

// Preview PDF Surat Perintah Tugas (inline di tab baru): halaman 1 surat tugas, halaman 2 surat
// perintah perjalanan dinas. Boleh dibuka pembuat, penanda tangan, dan karyawan yang ditugaskan.
Route::get('/surat-tugas/{record}/preview', function (\App\Models\SuratTugas\SuratTugas $record) {
    $userId = \Illuminate\Support\Facades\Auth::id();

    abort_unless(
        $record->created_by === $userId
            || $record->ttd_user_id === $userId
            || $record->peserta()->where('user_id', $userId)->exists(),
        403
    );

    return app(\App\Services\SuratTugas\SuratTugasPdfService::class)->preview($record);
})->name('surat-tugas.preview');
