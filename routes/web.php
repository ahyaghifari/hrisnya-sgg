<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceTemplateController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\Public\OrgUnitsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Publik (tanpa login) — kuis & umpan balik via QR. DINONAKTIFKAN 2026-08-29: semua
// pengerjaan kuis & umpan balik sekarang langsung di HRIS (login), tidak ada lagi akses
// publik. File PublicQuizPage/PublicFeedbackPage/QrCodeService & kolom *_public_token
// SENGAJA tidak dihapus — aktifkan lagi cukup uncomment blok ini.
// Route::middleware('throttle:20,1')->prefix('publik')->group(function () {
//     Route::get('/kuis/{token}', \App\Livewire\LMS\PublicQuizPage::class)->name('lms.public.quiz');
//     Route::get('/umpan-balik/{token}', \App\Livewire\LMS\PublicFeedbackPage::class)->name('lms.public.feedback');
// });

// Publik (tanpa login) — daftar Departemen/Divisi/Sub Divisi + kode integrasi, dipakai
// sistem lain buat cocokkan unit organisasi mereka ke unit yang sama di HRIS. Read-only,
// tanpa data sensitif. Lihat App\Http\Controllers\Public\OrgUnitsController.
Route::middleware('throttle:60,1')->prefix('publik')->group(function () {
    Route::get('/unit-organisasi', [OrgUnitsController::class, 'index'])->name('public.org-units');
    Route::get('/unit-organisasi.json', [OrgUnitsController::class, 'json'])->name('public.org-units.json');
});

// Route per modul sistem (1 modul = 1 file), semua inherit middleware('auth','portal.access')
// dari group ini lewat require - bukan group() terpisah per file, supaya middleware-nya
// TIDAK perlu diulang/disinkronkan manual di tiap file (lihat juga komentar di
// routes/sikartap.php).
Route::middleware(['auth', 'portal.access'])->group(function () {
    require __DIR__ . '/portal-akun.php';
    require __DIR__ . '/assessment.php';
    require __DIR__ . '/kepegawaian.php';
    require __DIR__ . '/presensi.php';
    require __DIR__ . '/keuangan.php';
    require __DIR__ . '/pembinaan.php';
    require __DIR__ . '/tugas.php';
    require __DIR__ . '/lms.php';
    require __DIR__ . '/rekrutmen.php';
    require __DIR__ . '/sikartap.php';
});

Route::get('/attendance/template/download', [AttendanceTemplateController::class, 'downloadTemplate'])
    ->name('attendance.template.downloa');

Route::get('/employees/import/template', [EmployeeImportController::class, 'downloadTemplate'])
    ->middleware(['auth'])
    ->name('employees.import.template');
