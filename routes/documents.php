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
