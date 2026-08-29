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
use App\Livewire\EmployeeAttendanceStatistics;
use App\Livewire\Kepegawaian\CreateKaryawan;
use App\Livewire\Kepegawaian\EditKaryawan;
use App\Livewire\Kepegawaian\CreateUserTransitionPage;
use App\Livewire\Kepegawaian\DocumentFormPage;
use App\Livewire\Kepegawaian\DocumentsPage;
use App\Livewire\Kepegawaian\KontrakPage;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Livewire\Kepegawaian\KepegawaianDashboardPage;
use App\Livewire\Kepegawaian\UserTransitionDetailPage;
use App\Livewire\Kepegawaian\UserTransitionsPage;
use App\Livewire\Keuangan\IncentivesPage;
use App\Livewire\Keuangan\KoperasiPage;
use App\Livewire\Keuangan\MappingKomponenGajiPage;
use App\Livewire\Keuangan\PajakPage;
use App\Livewire\Keuangan\PayrollDashboardPage;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Livewire\Keuangan\PayrollPeriodsPage;
use App\Livewire\Keuangan\PayrollsPage;
use App\Livewire\Keuangan\SalaryComponentsPage;
use App\Livewire\MyAttendanceRequests;
use App\Livewire\MyEmployees;
use App\Livewire\OrganizationStructure;
use App\Livewire\Pembinaan\PembinaanAtasanPage;
use App\Livewire\LMS\BankSoalPage;
use App\Livewire\LMS\CreateBankSoal;
use App\Livewire\LMS\CreateLmsCourse;
use App\Livewire\LMS\CreateQuiz;
use App\Livewire\LMS\ExternalTrainingDetailPage;
use App\Livewire\LMS\ExternalTrainingsPage;
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
use App\Livewire\Pelatihan\MyTrainingRequests;
use App\Livewire\Pelatihan\CourseFeedback;
use App\Livewire\Pelatihan\QuizAttempt;
use App\Livewire\Pelatihan\SubmitTrainingRequest;
use App\Livewire\Pelatihan\TrainingDetail;
use App\Livewire\Pelatihan\TrainingList;
use App\Livewire\Pelatihan\TrainingRequestDetail;
use App\Livewire\Presensi\AttendanceSettingsPage;
use App\Livewire\Presensi\CreateSchedulePage;
use App\Livewire\Presensi\CutiRecapPage;
use App\Livewire\Presensi\HolidayCalendarPage;
use App\Livewire\Presensi\ExtraShiftPage;
use App\Livewire\Presensi\ExtraShiftRulePage;
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

    Route::get('/notifikasi', \App\Livewire\NotificationsPage::class)
        ->name('notifikasi');

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

    Route::get('/profil-saya', EmployeeAttendanceStatistics::class)
        ->name('profil-saya');

    Route::get('/profil-saya/penggajian/{payroll}', \App\Livewire\Keuangan\MyPayrollSlipPage::class)
        ->name('profil-saya.penggajian.show');

    // Lampiran data diri (KTP/KK/dsb) — file terenkripsi di disk privat 'local', tidak boleh
    // dilayani lewat URL /storage langsung. Route ini decrypt di memory + gerbang izin
    // (pemilik akun atau permission lihat_lampiran_identitas_karyawan, sama seperti
    // EmployeeAttendanceStatistics::getCanViewProfileDocumentsProperty()).
    Route::get('/profil-saya/lampiran/{document}', function (\App\Models\User\UserProfileDocument $document) {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless(
            $document->user_id === $user->id
                || $user->can(\App\Enum\CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value),
            403
        );

        $ext = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));

        // Lampiran baru: terenkripsi di 'local'. Lampiran lama (< 2026-08-27): plaintext di
        // 'public' — dilayani apa adanya tanpa decrypt.
        $raw = \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path)
            ? \Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::disk('local')->get($document->file_path))
            : \Illuminate\Support\Facades\Storage::disk('public')->get($document->file_path);

        abort_if($raw === null, 404);

        return response($raw, 200, [
            'Content-Type' => $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg'),
            'Content-Disposition' => 'inline; filename="' . addslashes($document->label) . '.' . $ext . '"',
        ]);
    })->name('profil-saya.lampiran.show');

    Route::get('/profil-saya/penggajian/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
        abort_unless(
            $payroll->user_id === \Illuminate\Support\Facades\Auth::id() && $payroll->is_paid,
            403
        );

        return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
    })->name('profil-saya.penggajian.preview');

    // Path lama /data-diri masih bisa dibookmark orang - redirect permanen ke path baru,
    // bukan dihapus begitu saja (rename 2026-08-19, label sudah lama "Profil Saya" di nav,
    // URL-nya baru sekarang disamakan).
    Route::redirect('/data-diri', '/profil-saya', 301);

    Route::get('/struktur-organisasi', OrganizationStructure::class)
        ->name('organization-structure');

    Route::get('/my-staff', MyEmployees::class)
        ->name('my-staff');

    // Halaman TAMBAHAN, terpisah dari modul Pembinaan Karyawan (eks-Coaching & Counseling) HC
    // di grup route prefix('pembinaan') di bawah - lihat gate literal hasSubordinates() di
    // PembinaanAtasanPage::mount() (instruksi user 2026-08-17).
    Route::get('/my-staff/pembinaan', PembinaanAtasanPage::class)
        ->name('my-staff.pembinaan');

    Route::get('/riwayat-approval', RiwayatApprovalPage::class)
        ->name('riwayat-approval');

    require __DIR__ . '/sikartap.php';
    require __DIR__ . '/sarana-prasarana.php';

    Route::get('/hak-akses', \App\Livewire\RoleAccess\RoleAccessTreePage::class)
        ->name('hak-akses');

    Route::get('/role', \App\Livewire\RoleAccess\RolesPage::class)
        ->name('role');

    // Halaman detail karyawan dipakai 2 entry point: MyStaff (bawahan) & Karyawan (company-wide,
    // lihat grup route Kepegawaian di bawah) — makanya path-nya /karyawan, bukan /my-staff.
    Route::get('/karyawan/{record}', EmployeeAttendanceStatistics::class)
        ->name('karyawan.show');

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

    Route::get('/keuangan/pajak', PajakPage::class)
        ->name('keuangan.pajak');

    Route::get('/keuangan/data-rekening', \App\Livewire\Keuangan\DataRekeningPage::class)
        ->name('keuangan.data-rekening');

    Route::get('/keuangan/payroll/{payroll}', PayrollDetailPage::class)
        ->name('keuangan.payroll.show');

    Route::get('/keuangan/payroll/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
        abort_unless(\Illuminate\Support\Facades\Auth::user()->can('view', $payroll), 403);

        return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
    })->name('keuangan.payroll.preview');

    Route::get('/keuangan/komponen-gaji', SalaryComponentsPage::class)
        ->name('keuangan.komponen-gaji');

    Route::get('/keuangan/penerima-komponen-gaji', \App\Livewire\Keuangan\PayrollComponentRecipientsPage::class)
        ->name('keuangan.penerima-komponen-gaji');

    Route::get('/keuangan/insentif', IncentivesPage::class)
        ->name('keuangan.insentif');

    Route::get('/keuangan/tunjangan-eksekutif', \App\Livewire\Keuangan\ExecutiveAllowancesPage::class)
        ->name('keuangan.tunjangan-eksekutif');

    Route::get('/keuangan/koperasi', KoperasiPage::class)
        ->name('keuangan.koperasi');

    Route::get('/keuangan/tanggungan-bpjs', \App\Livewire\Keuangan\BpjsDependentsPage::class)
        ->name('keuangan.tanggungan-bpjs');

    Route::get('/keuangan/override-komponen-gaji', \App\Livewire\Keuangan\PayrollComponentOverridesPage::class)
        ->name('keuangan.override-komponen-gaji');

    // Model & permission-nya Payroll (prefix /keuangan), tapi nav-nya digabung ke dropdown
    // Learning Management System (lms-dropdown.blade.php), bukan dropdown Penggajian.
    Route::get('/keuangan/gaji-pemateri', \App\Livewire\Keuangan\GajiPemateriPage::class)
        ->name('keuangan.gaji-pemateri');

    Route::get('/keuangan/payroll/import-template/{type}', [PayrollManualLineTemplateController::class, 'downloadTemplate'])
        ->whereIn('type', ['bon', 'insentif'])
        ->name('keuangan.payroll.import-template');


    Route::get('/keuangan/mapping-komponen-gaji', MappingKomponenGajiPage::class)
        ->name('keuangan.mapping-komponen-gaji');

    Route::get('/keuangan/omset', \App\Livewire\Keuangan\OmsetPage::class)
        ->name('keuangan.omset');

    Route::get('/keuangan/jasa-unit-types', \App\Livewire\Keuangan\JasaUnitTypesPage::class)
        ->name('keuangan.jasa-unit-types');

    Route::get('/keuangan/jasa-unit', \App\Livewire\Keuangan\JasaUnitInputPage::class)
        ->name('keuangan.jasa-unit');

    Route::get('/keuangan/jasa-unit/import-template', [\App\Http\Controllers\JasaUnitDetailTemplateController::class, 'downloadTemplate'])
        ->name('keuangan.jasa-unit.import-template');

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

    // Didaftarkan sebelum wildcard /pelatihan/{course} di bawah — kalau kebalik, "quiz"/"umpan-balik"
    // akan ditangkap sebagai {course} dan gagal resolve (404) sebelum sempat sampai ke route ini.
    Route::get('/pelatihan/quiz/{meetingQuiz}', QuizAttempt::class)
        ->name('pelatihan.quiz');

    Route::get('/pelatihan/umpan-balik/{course}', CourseFeedback::class)
        ->name('pelatihan.feedback');

    Route::get('/pelatihan/pengajuan-saya', MyTrainingRequests::class)
        ->name('pelatihan.pengajuan-saya');

    Route::get('/pelatihan/pengajuan-saya/{record}', TrainingRequestDetail::class)
        ->name('pelatihan.pengajuan-saya.show');

    Route::get('/pelatihan/pengajuan', SubmitTrainingRequest::class)
        ->name('pelatihan.pengajuan');

    Route::get('/lms/honorarium', MasterHonorariumPage::class)
        ->name('pelatihan.honorarium');

    Route::get('/lms/pelatihan-eksternal', ExternalTrainingsPage::class)
        ->name('pelatihan.eksternal');

    Route::get('/lms/pelatihan-eksternal/{record}', ExternalTrainingDetailPage::class)
        ->name('pelatihan.eksternal.show');

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

    // Rekrutmen = modul sendiri (2026-08-28) - nav group terpisah, prefix /rekrutmen. Dulu
    // sempat dilebur ke /kepegawaian/lowongan; dikembalikan ke /rekrutmen. Permintaan Tenaga
    // Kerja TETAP di /kepegawaian (bagian Jabatan), bukan modul Rekrutmen.
    Route::redirect('/kepegawaian/lowongan', '/rekrutmen/lowongan', 301);
    Route::get('/kepegawaian/lowongan/{record}', fn (string $record) => redirect(route('rekrutmen.lowongan.show', $record), 301));

    Route::get('/rekrutmen/lowongan', JobOpeningsPage::class)
        ->name('rekrutmen.lowongan');

    Route::get('/rekrutmen/mcu', \App\Livewire\Rekrutmen\McuQueuePage::class)
        ->name('rekrutmen.mcu');

    Route::get('/rekrutmen/lowongan/{record}/edit', EditJobOpeningPage::class)
        ->name('rekrutmen.lowongan.edit');

    // {opening} = id lowongan HRIS ATAU id lowongan Rekrutmen yang belum terhubung — string,
    // bukan model binding (ApplicantsPage yang resolve).
    Route::get('/rekrutmen/lowongan/{opening}/pelamar', \App\Livewire\Rekrutmen\ApplicantsPage::class)
        ->name('rekrutmen.lowongan.pelamar');

    Route::get('/rekrutmen/lowongan/kandidat/{record}', CandidateApplicationDetailPage::class)
        ->name('rekrutmen.lowongan.kandidat');

    Route::get('/rekrutmen/lowongan/{record}', JobOpeningDetailPage::class)
        ->name('rekrutmen.lowongan.show');

    // {jobApplication} = ULID milik project Rekrutmen eksternal (bukan model HRIS) - string biasa.
    Route::get('/rekrutmen/pelamar/{jobApplication}/dokumen/{document}', [\App\Http\Controllers\Rekrutmen\CandidateDocumentProxyController::class, 'show'])
        ->name('rekrutmen.pelamar.dokumen');

    Route::get('/rekrutmen/pelamar/{jobApplication}/offering-letter/{type}', [\App\Http\Controllers\Rekrutmen\CandidateDocumentProxyController::class, 'offeringLetter'])
        ->where('type', 'recruiter|signed')
        ->name('rekrutmen.pelamar.offering-letter');

    Route::get('/rekrutmen/pelamar/{jobApplication}/mcu/{station}', \App\Livewire\Rekrutmen\McuFormPage::class)
        ->where('station', 'nurse|lab|radiology|doctor')
        ->name('rekrutmen.pelamar.mcu');

    Route::get('/rekrutmen/pelamar/{jobApplication}', \App\Livewire\Rekrutmen\ApplicantDetailPage::class)
        ->name('rekrutmen.pelamar.show');

    Route::get('/kepegawaian/permintaan', ManpowerRequestsPage::class)
        ->name('kepegawaian.permintaan');

    Route::get('/kepegawaian/permintaan/tambah/{draft?}', CreateManpowerRequestPage::class)
        ->name('kepegawaian.permintaan.create');

    Route::get('/kepegawaian/permintaan/{record}', ManpowerRequestDetailPage::class)
        ->name('kepegawaian.permintaan.show');
});

Route::get('/attendance/template/download', [AttendanceTemplateController::class, 'downloadTemplate'])
    ->name('attendance.template.downloa');

Route::get('/employees/import/template', [EmployeeImportController::class, 'downloadTemplate'])
    ->middleware(['auth'])
    ->name('employees.import.template');
