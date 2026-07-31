<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceTemplateController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\PayrollManualLineTemplateController;
use App\Livewire\AttendanceRequestDetail;
use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Livewire\Coaching\MyCoachingPage;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use App\Livewire\Dashboard;
use App\Livewire\EditMyEmployee;
use App\Livewire\EmployeeAttendanceStatistics;
use App\Livewire\Kepegawaian\CreateKaryawan;
use App\Livewire\Kepegawaian\EditKaryawan;
use App\Livewire\Kepegawaian\CreateUserTransitionPage;
use App\Livewire\Kepegawaian\DocumentFormPage;
use App\Livewire\Kepegawaian\DocumentsPage;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Livewire\Kepegawaian\KepegawaianDashboardPage;
use App\Livewire\Kepegawaian\UserTransitionDetailPage;
use App\Livewire\Kepegawaian\UserTransitionsPage;
use App\Livewire\Keuangan\IncentivesPage;
use App\Livewire\Keuangan\KoperasiPage;
use App\Livewire\Keuangan\MappingKomponenGajiPage;
use App\Livewire\Keuangan\PayrollDashboardPage;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Livewire\Keuangan\PayrollPeriodsPage;
use App\Livewire\Keuangan\PayrollsPage;
use App\Livewire\Keuangan\SalaryComponentsPage;
use App\Livewire\MyAttendanceRequests;
use App\Livewire\MyEmployees;
use App\Livewire\OrganizationStructure;
use App\Livewire\LMS\BankSoalPage;
use App\Livewire\LMS\CreateBankSoal;
use App\Livewire\LMS\CreateLmsCourse;
use App\Livewire\LMS\CreateQuiz;
use App\Livewire\LMS\LmsCategoriesPage;
use App\Livewire\LMS\LmsCourseBaselinesPage;
use App\Livewire\LMS\LmsCourseDetailPage;
use App\Livewire\LMS\LmsCoursesPage;
use App\Livewire\LMS\LmsFeedbackQuestionsPage;
use App\Livewire\LMS\LmsSettingsPage;
use App\Livewire\LMS\MasterHonorariumPage;
use App\Livewire\LMS\QuizzesPage;
use App\Livewire\LMS\TrainerDetailPage;
use App\Livewire\LMS\TrainersPage;
use App\Livewire\LMS\TrainingReportPage;
use App\Livewire\Pelatihan\QuizAttempt;
use App\Livewire\Pelatihan\TrainingDetail;
use App\Livewire\Pelatihan\TrainingList;
use App\Livewire\Presensi\AttendanceSettingsPage;
use App\Livewire\Presensi\CreateSchedulePage;
use App\Livewire\Presensi\CutiRecapPage;
use App\Livewire\Presensi\HolidayCalendarPage;
use App\Livewire\Presensi\ExtraShiftPage;
use App\Livewire\Presensi\HolidaysPage;
use App\Livewire\Presensi\IzinRecapPage;
use App\Livewire\Presensi\LemburRecapPage;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Livewire\Presensi\ScheduleAssignmentsPage;
use App\Livewire\Presensi\ScheduleDetailPage;
use App\Livewire\Presensi\SchedulesPage;
use App\Livewire\Rekrutmen\CandidateApplicationDetailPage;
use App\Livewire\Rekrutmen\EditJobOpeningPage;
use App\Livewire\Rekrutmen\CreateManpowerRequestPage;
use App\Livewire\Rekrutmen\JobOpeningDetailPage;
use App\Livewire\Rekrutmen\JobOpeningsPage;
use App\Livewire\Rekrutmen\ManpowerRequestDetailPage;
use App\Livewire\Rekrutmen\ManpowerRequestsPage;
use App\Http\Controllers\Pwa\PushSubscriptionController;
use App\Livewire\Pwa\Akun as PwaAkun;
use App\Livewire\Pwa\NotificationSettingsPage;
use App\Livewire\Pwa\UbahPassword as PwaUbahPassword;
use App\Livewire\RiwayatApprovalPage;
use App\Livewire\SubmitLeaveRequest;
use App\Livewire\SubmitOvertimeRequest;
use App\Livewire\SubmitPermissionRequest;
use App\Livewire\Task\TaskListPage;
use App\Livewire\Task\TaskSettingsPage;
use Illuminate\Support\Facades\Route;


Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Publik (tanpa login) — kuis & umpan balik via QR, lihat planning §5. Throttle generous
// (bukan 5/menit seperti /login) karena banyak orang bisa buka bareng dari 1 QR di 1 ruangan.
Route::middleware('throttle:20,1')->prefix('publik')->group(function () {
    Route::get('/kuis/{token}', \App\Livewire\LMS\PublicQuizPage::class)->name('lms.public.quiz');
    Route::get('/umpan-balik/{token}', \App\Livewire\LMS\PublicFeedbackPage::class)->name('lms.public.feedback');
});

Route::middleware(['auth', 'portal.access'])->group(function () {
    Route::get('/', Dashboard::class)
        ->name('dashboard');

    Route::get('/akun', PwaAkun::class)
        ->name('akun');

    Route::get('/akun/ubah-password', PwaUbahPassword::class)
        ->name('akun.ubah-password');

    Route::get('/akun/notifikasi', NotificationSettingsPage::class)
        ->name('akun.notifikasi');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])
        ->name('push.unsubscribe');

    Route::get('/data-diri', EmployeeAttendanceStatistics::class)
        ->name('data-diri');

    Route::get('/data-diri/penggajian/{payroll}', \App\Livewire\Keuangan\MyPayrollSlipPage::class)
        ->name('data-diri.penggajian.show');

    Route::get('/data-diri/penggajian/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
        abort_unless(
            $payroll->user_id === \Illuminate\Support\Facades\Auth::id() && $payroll->is_paid,
            403
        );

        return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
    })->name('data-diri.penggajian.preview');

    Route::get('/struktur-organisasi', OrganizationStructure::class)
        ->name('organization-structure');

    Route::get('/my-staff', MyEmployees::class)
        ->name('my-staff');

    Route::get('/my-staff/{record}/edit', EditMyEmployee::class)
        ->name('my-staff.edit');

    Route::get('/riwayat-approval', RiwayatApprovalPage::class)
        ->name('riwayat-approval');

    // Halaman detail karyawan dipakai 2 entry point: MyStaff (bawahan) & Karyawan (company-wide,
    // lihat grup route Kepegawaian di bawah) — makanya path-nya /karyawan, bukan /my-staff.
    Route::get('/karyawan/{record}', EmployeeAttendanceStatistics::class)
        ->name('karyawan.show');

    Route::get('/kepegawaian/dashboard', KepegawaianDashboardPage::class)
        ->name('kepegawaian.dashboard');

    Route::get('/kepegawaian/karyawan', KaryawanPage::class)
        ->name('kepegawaian.karyawan');

    Route::get('/kepegawaian/karyawan/tambah', CreateKaryawan::class)
        ->name('kepegawaian.karyawan.create');

    Route::get('/kepegawaian/karyawan/{record}/edit', EditKaryawan::class)
        ->name('kepegawaian.karyawan.edit');

    Route::get('/kepegawaian/dokumen', DocumentsPage::class)
        ->name('kepegawaian.dokumen');

    Route::get('/kepegawaian/dokumen/tambah', DocumentFormPage::class)
        ->name('kepegawaian.dokumen.create');

    Route::get('/kepegawaian/dokumen/{record}/edit', DocumentFormPage::class)
        ->name('kepegawaian.dokumen.edit');

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

    Route::get('/kepegawaian/job-evaluation', \App\Livewire\Kepegawaian\JobEvaluationPage::class)
        ->name('kepegawaian.job-evaluation');

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

    Route::get('/keuangan/dashboard', PayrollDashboardPage::class)
        ->name('keuangan.dashboard');

    Route::get('/keuangan/periode-gaji', PayrollPeriodsPage::class)
        ->name('keuangan.periode-gaji');

    Route::get('/keuangan/payroll', PayrollsPage::class)
        ->name('keuangan.payroll');

    Route::get('/keuangan/payroll/{payroll}', PayrollDetailPage::class)
        ->name('keuangan.payroll.show');

    Route::get('/keuangan/payroll/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
        abort_unless(\Illuminate\Support\Facades\Auth::user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
    })->name('keuangan.payroll.preview');

    Route::get('/keuangan/komponen-gaji', SalaryComponentsPage::class)
        ->name('keuangan.komponen-gaji');

    Route::get('/keuangan/penerima-komponen-gaji', \App\Livewire\Keuangan\PayrollComponentRecipientsPage::class)
        ->name('keuangan.penerima-komponen-gaji');

    Route::get('/keuangan/insentif', IncentivesPage::class)
        ->name('keuangan.insentif');

    Route::get('/keuangan/koperasi', KoperasiPage::class)
        ->name('keuangan.koperasi');

    Route::get('/keuangan/tanggungan-bpjs', \App\Livewire\Keuangan\BpjsDependentsPage::class)
        ->name('keuangan.tanggungan-bpjs');

    Route::get('/keuangan/payroll/import-template/{type}', [PayrollManualLineTemplateController::class, 'downloadTemplate'])
        ->whereIn('type', ['bon', 'insentif'])
        ->name('keuangan.payroll.import-template');


    Route::get('/keuangan/mapping-komponen-gaji', MappingKomponenGajiPage::class)
        ->name('keuangan.mapping-komponen-gaji');

    Route::get('/keuangan/omset', \App\Livewire\Keuangan\OmsetPage::class)
        ->name('keuangan.omset');

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

    Route::get('/tugas', TaskListPage::class)
        ->name('tugas');

    Route::get('/tugas/pengaturan', TaskSettingsPage::class)
        ->name('tugas.pengaturan');

    Route::get('/pelatihan', TrainingList::class)
        ->name('pelatihan');

    // Didaftarkan sebelum wildcard /pelatihan/{course} di bawah — kalau kebalik, "quiz" akan
    // ditangkap sebagai {course} dan gagal resolve (404) sebelum sempat sampai ke route ini.
    Route::get('/pelatihan/quiz/{meetingQuiz}', QuizAttempt::class)
        ->name('pelatihan.quiz');

    Route::get('/lms/honorarium', MasterHonorariumPage::class)
        ->name('pelatihan.honorarium');

    Route::get('/lms/evaluasi', LmsFeedbackQuestionsPage::class)
        ->name('pelatihan.evaluasi');

    Route::get('/lms/pengaturan', LmsSettingsPage::class)
        ->name('pelatihan.pengaturan');

    Route::get('/lms/kategori', LmsCategoriesPage::class)
        ->name('pelatihan.kategori');

    Route::get('/lms/baseline', LmsCourseBaselinesPage::class)
        ->name('pelatihan.baseline');

    Route::get('/lms/laporan', TrainingReportPage::class)
        ->name('pelatihan.laporan');

    // Bank Soal — padanan portal dari Filament QuestionBanksResource (/admin/lms/question-banks).
    // "tambah" harus didaftarkan SEBELUM wildcard /lms/bank-soal/{soal} kalau nanti ditambah.
    Route::get('/lms/bank-soal', BankSoalPage::class)
        ->name('pelatihan.bank-soal');

    Route::get('/lms/bank-soal/tambah', CreateBankSoal::class)
        ->name('pelatihan.bank-soal.create');

    // Kuis — padanan portal dari Filament QuizzesResource (/admin/lms/quizzes).
    // "tambah" harus didaftarkan SEBELUM wildcard /lms/kuis/{kuis} kalau nanti ditambah.
    Route::get('/lms/kuis', QuizzesPage::class)
        ->name('pelatihan.kuis');

    Route::get('/lms/kuis/tambah', CreateQuiz::class)
        ->name('pelatihan.kuis.create');

    Route::get('/lms/kuis/{quiz}/ubah', CreateQuiz::class)
        ->name('pelatihan.kuis.edit');

    Route::get('/lms/pemateri', TrainersPage::class)
        ->name('pelatihan.pemateri');

    Route::get('/lms/pemateri/{trainer}', TrainerDetailPage::class)
        ->name('pelatihan.pemateri.show');

    Route::get('/lms/pelatihan', LmsCoursesPage::class)
        ->name('pelatihan.kelola');

    Route::get('/lms/pelatihan/tambah', CreateLmsCourse::class)
        ->name('pelatihan.kelola.create');

    Route::get('/lms/pelatihan/{course}', LmsCourseDetailPage::class)
        ->name('pelatihan.kelola.show');

    Route::get('/pelatihan/{course}', TrainingDetail::class)
        ->name('pelatihan.show');

    // Rekrutmen digabung ke bawah Kepegawaian (permission SISTEM_REKRUTMEN dihapus, gerbang
    // dropdown-nya sekarang ikut $canAccessKepegawaian di nav.blade.php) - path & route name
    // dipindah dari /rekrutmen/... ke /kepegawaian/..., tapi kelas Livewire-nya sendiri
    // dibiarkan tetap di namespace App\Livewire\Rekrutmen (cuma rename routing/nav, bukan
    // reorganisasi folder).
    Route::get('/kepegawaian/lowongan', JobOpeningsPage::class)
        ->name('kepegawaian.lowongan');

    Route::get('/kepegawaian/lowongan/{record}/edit', EditJobOpeningPage::class)
        ->name('kepegawaian.lowongan.edit');

    Route::get('/kepegawaian/lowongan/kandidat/{record}', CandidateApplicationDetailPage::class)
        ->name('kepegawaian.lowongan.kandidat');

    Route::get('/kepegawaian/lowongan/{record}', JobOpeningDetailPage::class)
        ->name('kepegawaian.lowongan.show');

    Route::get('/kepegawaian/permintaan', ManpowerRequestsPage::class)
        ->name('kepegawaian.permintaan');

    Route::get('/kepegawaian/permintaan/tambah', CreateManpowerRequestPage::class)
        ->name('kepegawaian.permintaan.create');

    Route::get('/kepegawaian/permintaan/{record}', ManpowerRequestDetailPage::class)
        ->name('kepegawaian.permintaan.show');
});

Route::get('/attendance/template/download', [AttendanceTemplateController::class, 'downloadTemplate'])
    ->name('attendance.template.downloa');

Route::get('/employees/import/template', [EmployeeImportController::class, 'downloadTemplate'])
    ->middleware(['auth'])
    ->name('employees.import.template');
