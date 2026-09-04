<?php

use App\Livewire\AttendanceRequestDetail;
use App\Livewire\MyAttendanceRequests;
use App\Livewire\Presensi\AttendanceSettingsPage;
use App\Livewire\Presensi\CreateSchedulePage;
use App\Livewire\Presensi\CutiRecapPage;
use App\Livewire\Presensi\ExtraShiftPage;
use App\Livewire\Presensi\ExtraShiftRulePage;
use App\Livewire\Presensi\HolidayCalendarPage;
use App\Livewire\Presensi\HolidaysPage;
use App\Livewire\Presensi\IzinRecapPage;
use App\Livewire\Presensi\LemburRecapPage;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Livewire\Presensi\ScheduleAssignmentsPage;
use App\Livewire\Presensi\ScheduleDetailPage;
use App\Livewire\Presensi\SchedulesPage;
use App\Livewire\SubmitLeaveRequest;
use App\Livewire\SubmitOvertimeRequest;
use App\Livewire\SubmitPermissionRequest;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Presensi & Absensi (jadwal, pengajuan cuti/izin/lembur, tukar shift, rekap).
 * Dipisah dari routes/web.php (2026-09-04) - lihat komentar di routes/sikartap.php untuk
 * konvensi split per-modul.
 */
Route::get('/pengajuan-absensi', MyAttendanceRequests::class)
    ->name('my-attendance-requests');

Route::get('/pengajuan-absensi/cuti', SubmitLeaveRequest::class)
    ->name('my-attendance-requests.leave');

Route::get('/pengajuan-absensi/izin', SubmitPermissionRequest::class)
    ->name('my-attendance-requests.permission');

Route::get('/pengajuan-absensi/lembur', SubmitOvertimeRequest::class)
    ->name('my-attendance-requests.overtime');

Route::get('/pengajuan-absensi/{record}', AttendanceRequestDetail::class)
    ->name('my-attendance-requests.show');

Route::get('/presensi/dashboard', \App\Livewire\Presensi\AttendanceDashboardPage::class)
    ->name('presensi.dashboard');

Route::get('/presensi/jadwal', SchedulesPage::class)
    ->name('presensi.jadwal');

Route::get('/presensi/jadwal/tambah', CreateSchedulePage::class)
    ->name('presensi.jadwal.tambah');

Route::get('/presensi/jadwal/{schedule}/pengguna', ScheduleAssignmentsPage::class)
    ->name('presensi.jadwal.pengguna');

Route::get('/presensi/jadwal/{schedule}/edit', CreateSchedulePage::class)
    ->name('presensi.jadwal.edit');

Route::get('/presensi/jadwal/{schedule}', ScheduleDetailPage::class)
    ->name('presensi.jadwal.show');

Route::get('/presensi/pengaturan', AttendanceSettingsPage::class)
    ->name('presensi.pengaturan');

Route::get('/presensi/hari-libur', HolidaysPage::class)
    ->name('presensi.hari-libur');

Route::get('/presensi/extra-shift', ExtraShiftPage::class)
    ->name('presensi.extra-shift');

Route::get('/presensi/extra-shift-rule', ExtraShiftRulePage::class)
    ->name('presensi.extra-shift-rule');

Route::get('/presensi/jadwal-saya', \App\Livewire\Presensi\JadwalSayaPage::class)
    ->name('presensi.jadwal-saya');

Route::get('/tukar-shift', \App\Livewire\Presensi\MyShiftSwapRequests::class)
    ->name('tukar-shift');

Route::get('/tukar-shift/{record}', \App\Livewire\Presensi\ShiftSwapRequestDetail::class)
    ->name('tukar-shift.show');

Route::get('/presensi/rekap-izin', IzinRecapPage::class)
    ->name('presensi.rekap-izin');

Route::get('/presensi/rekap-cuti', CutiRecapPage::class)
    ->name('presensi.rekap-cuti');

Route::get('/presensi/rekap-lembur', LemburRecapPage::class)
    ->name('presensi.rekap-lembur');

Route::get('/presensi/rekap-presensi', RekapPresensiPage::class)
    ->name('presensi.rekap-presensi');

// Read-only, permission null (guard/self-service biasa) — versi karyawan biasa dari
// halaman kelola di atas. Belum ada link masuk di nav manapun, lihat
// planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §9.3.
Route::get('/presensi/kalender-libur', HolidayCalendarPage::class)
    ->name('presensi.kalender-libur');
