<?php

use Illuminate\Support\Facades\Route;

/**
 * Route modul Assessment (Penilaian Karyawan). Dipisah dari routes/web.php (2026-09-04) -
 * lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 */

// Literal path harus terdaftar SEBELUM /penilaian/{assessmentUser} di bawah, supaya
// "template" tidak ketangkap wildcard {assessmentUser}.
Route::get('/penilaian/template', \App\Livewire\Assessment\TemplatePenilaianPage::class)
    ->name('penilaian.template');

Route::get('/penilaian/template/tambah', \App\Livewire\Assessment\CreateTemplatePenilaianPage::class)
    ->name('penilaian.template.create');

Route::get('/penilaian/template/{template}/edit', \App\Livewire\Assessment\EditTemplatePenilaianPage::class)
    ->name('penilaian.template.edit');

Route::get('/penilaian/jadwal', \App\Livewire\Assessment\JadwalPenilaianPage::class)
    ->name('penilaian.jadwal');

Route::get('/penilaian/jadwal/tambah', \App\Livewire\Assessment\CreateJadwalPenilaianPage::class)
    ->name('penilaian.jadwal.create');

Route::get('/penilaian/jadwal/{assessment}/edit', \App\Livewire\Assessment\EditJadwalPenilaianPage::class)
    ->name('penilaian.jadwal.edit');

Route::get('/penilaian/daftar', \App\Livewire\Assessment\DaftarPenilaianPage::class)
    ->name('penilaian.daftar');

Route::get('/penilaian/daftar/tambah', \App\Livewire\Assessment\CreatePenilaianKaryawanPage::class)
    ->name('penilaian.daftar.tambah');

Route::get('/penilaian/{assessmentUser}', \App\Livewire\AssessmentUserForm::class)
    ->name('assessment.show');
