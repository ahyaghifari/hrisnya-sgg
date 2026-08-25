<?php

use App\Livewire\Sarpras\SarprasRequestDetailPage;
use App\Livewire\Sarpras\SarprasRequestListPage;
use App\Livewire\Sarpras\SubmitSarprasRequestPage;
use Illuminate\Support\Facades\Route;

/**
 * Route portal Sarana & Prasarana, dipisah dari routes/web.php — persis pola sikartap.php
 * (instruksi eksplisit user 2026-08-25). Di-require dari dalam grup middleware auth+
 * portal.access di web.php, jadi tetap kena middleware yang sama tanpa perlu declare ulang.
 */
Route::get('/sarana-prasarana', SarprasRequestListPage::class)
    ->name('sarana-prasarana');

Route::get('/sarana-prasarana/ajukan', SubmitSarprasRequestPage::class)
    ->name('sarana-prasarana.ajukan');

Route::get('/sarana-prasarana/{sarprasRequest}', SarprasRequestDetailPage::class)
    ->name('sarana-prasarana.show');
