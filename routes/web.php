<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceTemplateController;
use App\Http\Controllers\EmployeeImportController;
use App\Livewire\AttendanceRequestDetail;
use App\Livewire\Dashboard;
use App\Livewire\EditMyEmployee;
use App\Livewire\EmployeeAttendanceStatistics;
use App\Livewire\Kanban\CreateKanbanBoard;
use App\Livewire\Kanban\KanbanBoardDetail;
use App\Livewire\Kanban\KanbanBoardsList;
use App\Livewire\Kanban\KanbanPersonalTasks;
use App\Livewire\Keuangan\IncentivesPage;
use App\Livewire\Keuangan\SalaryComponentMappingPage;
use App\Livewire\Keuangan\SalaryComponentsPage;
use App\Livewire\MyAttendanceRequests;
use App\Livewire\MyEmployees;
use App\Livewire\OrganizationStructure;
use App\Livewire\Pelatihan\QuizAttempt;
use App\Livewire\Pelatihan\TrainingDetail;
use App\Livewire\Pelatihan\TrainingList;
use App\Http\Controllers\Pwa\PushSubscriptionController;
use App\Livewire\Pwa\Akun as PwaAkun;
use App\Livewire\Pwa\UbahPassword as PwaUbahPassword;
use App\Livewire\SubmitLeaveRequest;
use App\Livewire\SubmitOvertimeRequest;
use App\Livewire\SubmitPermissionRequest;
use Illuminate\Support\Facades\Route;


Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'portal.access'])->group(function () {
    Route::get('/', Dashboard::class)
        ->name('dashboard');

    Route::get('/akun', PwaAkun::class)
        ->name('akun');

    Route::get('/akun/ubah-password', PwaUbahPassword::class)
        ->name('akun.ubah-password');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])
        ->name('push.unsubscribe');

    Route::get('/data-diri', EmployeeAttendanceStatistics::class)
        ->name('data-diri');

    Route::get('/struktur-organisasi', OrganizationStructure::class)
        ->name('organization-structure');

    Route::get('/my-staff', MyEmployees::class)
        ->name('my-staff');

    Route::get('/my-staff/{record}/edit', EditMyEmployee::class)
        ->name('my-staff.edit');

    Route::get('/my-staff/{record}', EmployeeAttendanceStatistics::class)
        ->name('my-staff.show');

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

    Route::get('/keuangan/komponen-gaji', SalaryComponentsPage::class)
        ->name('keuangan.komponen-gaji');

    Route::get('/keuangan/mapping-komponen-gaji', SalaryComponentMappingPage::class)
        ->name('keuangan.mapping-komponen-gaji');

    Route::get('/keuangan/insentif', IncentivesPage::class)
        ->name('keuangan.insentif');

    Route::get('/kanban', KanbanBoardsList::class)
        ->name('kanban');

    Route::get('/kanban/baru', CreateKanbanBoard::class)
        ->name('kanban.create');

    Route::get('/kanban/pribadi', KanbanPersonalTasks::class)
        ->name('kanban.personal');

    Route::get('/kanban/{board}', KanbanBoardDetail::class)
        ->name('kanban.show');

    Route::get('/pelatihan', TrainingList::class)
        ->name('pelatihan');

    // Didaftarkan sebelum wildcard /pelatihan/{course} di bawah — kalau kebalik, "quiz" akan
    // ditangkap sebagai {course} dan gagal resolve (404) sebelum sempat sampai ke route ini.
    Route::get('/pelatihan/quiz/{meetingQuiz}', QuizAttempt::class)
        ->name('pelatihan.quiz');

    Route::get('/pelatihan/{course}', TrainingDetail::class)
        ->name('pelatihan.show');
});

Route::get('/attendance/template/download', [AttendanceTemplateController::class, 'downloadTemplate'])
    ->name('attendance.template.downloa');

Route::get('/employees/import/template', [EmployeeImportController::class, 'downloadTemplate'])
    ->middleware(['auth'])
    ->name('employees.import.template');
