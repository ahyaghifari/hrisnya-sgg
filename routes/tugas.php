<?php

use App\Livewire\Task\TaskArchivePage;
use App\Livewire\Task\TaskListPage;
use App\Livewire\Task\TaskMenuPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Tugas (Task). Dipisah dari routes/web.php (2026-09-04) - lihat komentar di
 * routes/sikartap.php untuk konvensi split per-modul.
 *
 * /tugas sekarang cuma "permukaan" (tile menu) - papan/list sebenarnya masing-masing punya
 * URL sendiri di /tugas/papan/* (redesign 2026-09-19, lihat planning wiggly-cooking-star).
 */
Route::get('/tugas', TaskMenuPage::class)
    ->name('tugas');

Route::get('/tugas/papan/board/{project}', TaskListPage::class)
    ->name('tugas.papan.board');

Route::get('/tugas/papan/{scope}', TaskListPage::class)
    ->whereIn('scope', ['todo', 'saya', 'individu', 'tim'])
    ->name('tugas.papan');

Route::get('/tugas/arsip', TaskArchivePage::class)
    ->name('tugas.arsip');

Route::get('/tugas/surat-tugas/{surat}', \App\Livewire\Task\SuratTugasDetailPage::class)
    ->name('tugas.surat-tugas');
