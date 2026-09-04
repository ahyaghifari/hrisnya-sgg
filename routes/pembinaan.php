<?php

use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Livewire\Coaching\MyCoachingPage;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Pembinaan Karyawan (eks-Coaching & Counseling). Dipisah dari routes/web.php
 * (2026-09-04) - lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 */
Route::get('/coaching', MyCoachingPage::class)
    ->name('coaching.saya');

Route::get('/counseling', CounselingPage::class)
    ->name('counseling');

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
});
