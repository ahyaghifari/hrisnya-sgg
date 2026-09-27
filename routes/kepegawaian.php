<?php

use App\Livewire\Kepegawaian\CreateKaryawan;
use App\Livewire\Kepegawaian\CreateUserTransitionPage;
use App\Livewire\Kepegawaian\EditKaryawan;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Livewire\Kepegawaian\KepegawaianDashboardPage;
use App\Livewire\Kepegawaian\KontrakPage;
use App\Livewire\Kepegawaian\OrientasiKaryawanPage;
use App\Livewire\Kepegawaian\UserTransitionDetailPage;
use App\Livewire\Kepegawaian\UserTransitionsPage;
use App\Livewire\Rekrutmen\CreateManpowerRequestPage;
use App\Livewire\Rekrutmen\ManpowerRequestDetailPage;
use App\Livewire\Rekrutmen\ManpowerRequestsPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Kepegawaian. Dipisah dari routes/web.php (2026-09-04) - lihat komentar di
 * routes/sikartap.php untuk konvensi split per-modul. Permintaan Tenaga Kerja (namespace
 * App\Livewire\Rekrutmen, sisa-sisa sebelum modul Rekrutmen dipisah) TETAP di sini, bukan
 * routes/rekrutmen.php - lihat catatan di routes/rekrutmen.php.
 */
Route::get('/kepegawaian/dashboard', KepegawaianDashboardPage::class)
    ->name('kepegawaian.dashboard');

Route::get('/kepegawaian/karyawan', KaryawanPage::class)
    ->name('kepegawaian.karyawan');

Route::get('/kepegawaian/karyawan/tambah', CreateKaryawan::class)
    ->name('kepegawaian.karyawan.create');

Route::get('/kepegawaian/karyawan/{record}/edit', EditKaryawan::class)
    ->name('kepegawaian.karyawan.edit');

// Data ledger periode kontrak & orientasi berjalan (Kepegawaian > Status Karyawan). Alur
// PENGAJUAN perpanjangan/penilaian ada di dropdown Sikartap (sikartap.kontrak/.orientasi).
Route::get('/kepegawaian/kontrak', KontrakPage::class)
    ->name('kepegawaian.kontrak');

Route::get('/kepegawaian/orientasi', OrientasiKaryawanPage::class)
    ->name('kepegawaian.orientasi');

// Self-service Orientasi (karyawan kategori Orientasi mengajukan penilaian) — Baris 1 nav,
// pola Sikartap. Sisi HR (kelola pengajuan penilaian) ada di routes/sikartap.php
// (sikartap.orientasi), payung alur perjalanan status karyawan.
Route::get('/orientasi', \App\Livewire\Kepegawaian\OrientasiPage::class)
    ->name('orientasi');
Route::get('/orientasi/{record}', \App\Livewire\Kepegawaian\OrientasiDetailPage::class)
    ->name('orientasi.show');

// Self-service Perpanjangan Kontrak (karyawan kategori Kontrak) — pola Orientasi, lepas
// Approval Engine. Sisi HR (kelola pengajuan) di routes/sikartap.php (sikartap.kontrak).
Route::get('/kontrak-saya', \App\Livewire\Kepegawaian\KontrakSayaPage::class)
    ->name('kontrak.saya');

Route::get('/kepegawaian/perubahan-status', UserTransitionsPage::class)
    ->name('kepegawaian.mutasi-karyawan');

Route::get('/kepegawaian/perubahan-status/tambah', CreateUserTransitionPage::class)
    ->name('kepegawaian.mutasi-karyawan.create');

Route::get('/kepegawaian/perubahan-status/{record}', UserTransitionDetailPage::class)
    ->name('kepegawaian.mutasi-karyawan.show');

Route::get('/kepegawaian/jabatan', \App\Livewire\Kepegawaian\JabatanPage::class)
    ->name('kepegawaian.jabatan');

Route::get('/kepegawaian/jabatan/tambah', \App\Livewire\Kepegawaian\CreateJabatanPage::class)
    ->name('kepegawaian.jabatan.tambah');

Route::get('/kepegawaian/jabatan/{record}/edit', \App\Livewire\Kepegawaian\EditJabatanPage::class)
    ->name('kepegawaian.jabatan.edit');

Route::get('/kepegawaian/jabatan/{record}/tambah-kuota', \App\Livewire\Kepegawaian\CreateJabatanQuotaPage::class)
    ->name('kepegawaian.jabatan.tambah-kuota');

Route::get('/kepegawaian/job-evaluation', \App\Livewire\Kepegawaian\JobEvaluationPage::class)
    ->name('kepegawaian.job-evaluation');

Route::get('/kepegawaian/pengaturan', \App\Livewire\Kepegawaian\EmploymentSettingsPage::class)
    ->name('kepegawaian.pengaturan');

Route::get('/kepegawaian/alur-persetujuan', \App\Livewire\Kepegawaian\AlurPersetujuanListPage::class)
    ->name('kepegawaian.alur-persetujuan');

Route::get('/kepegawaian/alur-persetujuan/tambah', \App\Livewire\Kepegawaian\AlurPersetujuanFormPage::class)
    ->name('kepegawaian.alur-persetujuan.tambah');

Route::get('/kepegawaian/alur-persetujuan/{flow}/edit', \App\Livewire\Kepegawaian\AlurPersetujuanFormPage::class)
    ->name('kepegawaian.alur-persetujuan.edit');

Route::get('/kepegawaian/permintaan', ManpowerRequestsPage::class)
    ->name('kepegawaian.permintaan');

Route::get('/kepegawaian/permintaan/tambah/{draft?}', CreateManpowerRequestPage::class)
    ->name('kepegawaian.permintaan.create');

Route::get('/kepegawaian/permintaan/{record}', ManpowerRequestDetailPage::class)
    ->name('kepegawaian.permintaan.show');

Route::get('/kepegawaian/merit-poin', \App\Livewire\Kepegawaian\MeritPointPage::class)
    ->name('kepegawaian.merit-poin');
