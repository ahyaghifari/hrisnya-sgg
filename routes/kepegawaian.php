<?php

use App\Livewire\Kepegawaian\CreateKaryawan;
use App\Livewire\Kepegawaian\CreateUserTransitionPage;
use App\Livewire\Kepegawaian\DocumentFormPage;
use App\Livewire\Kepegawaian\DocumentsPage;
use App\Livewire\Kepegawaian\EditKaryawan;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Livewire\Kepegawaian\KepegawaianDashboardPage;
use App\Livewire\Kepegawaian\KontrakPage;
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

Route::get('/kepegawaian/karyawan/import', \App\Livewire\Kepegawaian\KaryawanImportPage::class)
    ->name('kepegawaian.karyawan.import');

Route::get('/kepegawaian/karyawan/import/template', [\App\Http\Controllers\KaryawanAccountImportController::class, 'downloadTemplate'])
    ->name('kepegawaian.karyawan.import.template');

Route::get('/kepegawaian/dokumen', DocumentsPage::class)
    ->name('kepegawaian.dokumen');

Route::get('/kepegawaian/dokumen/tambah', DocumentFormPage::class)
    ->name('kepegawaian.dokumen.create');

Route::get('/kepegawaian/dokumen/{record}/edit', DocumentFormPage::class)
    ->name('kepegawaian.dokumen.edit');

Route::get('/kepegawaian/kontrak', KontrakPage::class)
    ->name('kepegawaian.kontrak');

// Self-service Orientasi (karyawan kategori Orientasi mengajukan penilaian) — Baris 1 nav,
// pola Sikartap. Halaman indikator (HR) tetap di grup Kepegawaian.
Route::get('/orientasi', \App\Livewire\Kepegawaian\OrientasiPage::class)
    ->name('orientasi');
Route::get('/orientasi/{record}', \App\Livewire\Kepegawaian\OrientasiDetailPage::class)
    ->name('orientasi.show');
Route::get('/kepegawaian/orientasi', \App\Livewire\Kepegawaian\OrientasiKelolaPage::class)
    ->name('kepegawaian.orientasi');

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
