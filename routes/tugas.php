<?php

use App\Livewire\Task\TaskListPage;
use App\Livewire\Task\TaskSettingsPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Tugas (Task). Dipisah dari routes/web.php (2026-09-04) - lihat komentar di
 * routes/sikartap.php untuk konvensi split per-modul.
 */
Route::get('/tugas', TaskListPage::class)
    ->name('tugas');

Route::get('/tugas/pengaturan', TaskSettingsPage::class)
    ->name('tugas.pengaturan');
