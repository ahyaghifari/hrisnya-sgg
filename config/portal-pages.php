<?php

/**
 * Registry halaman portal karyawan (Livewire, di luar Filament).
 *
 * Satu sumber kebenaran untuk "halaman ini butuh apa supaya bisa diakses" — dipakai oleh
 * middleware `App\Http\Middleware\EnsurePortalPageAccess` (dipasang di routes/web.php)
 * supaya guard-nya tidak cuma ditulis manual di dalam mount() tiap komponen (gampang lupa,
 * lihat riwayat: route /dashboard sempat tidak punya cek granular sama sekali).
 *
 * Tiap entry (key = nama route):
 * - 'label'      : buat ditampilkan di nav kalau suatu saat nav mau baca dari sini juga.
 * - 'permission' : nama permission Spatie (string) yang wajib dimiliki user, atau null
 *                  kalau cukup login (tidak butuh permission spesifik).
 * - 'guard'      : nama method public tanpa argumen di App\Models\User yang harus return
 *                  true, dipakai buat kondisi kepemilikan/role yang bukan permission-string
 *                  biasa (mis. "boleh akses menu Karyawan" = hasSubordinates() ATAU
 *                  canBrowseAllCompanies() — sudah disatukan di User::canAccessKaryawanMenu()).
 *
 * CATATAN PENTING: route yang TIDAK terdaftar di sini otomatis LOLOS (tidak diblokir) —
 * ini bukan whitelist ketat, karena banyak halaman portal cuma butuh login biasa (self-service:
 * ajukan cuti/izin/lembur milik sendiri, dst) dan sudah cukup dijaga middleware `auth`.
 * Guna registry ini adalah buat halaman yang MEMANG butuh permission/kondisi tambahan,
 * supaya tidak lagi ditulis manual & bisa nyimpang antara satu tempat dengan tempat lain.
 */

return [
    'dashboard' => [
        'label' => 'Beranda',
        'permission' => null,
        'guard' => null,
    ],

    'data-diri' => [
        'label' => 'Data Diri',
        'permission' => null,
        'guard' => null,
    ],

    'akun' => [
        'label' => 'Akun',
        'permission' => null,
        'guard' => null,
    ],

    'akun.ubah-password' => [
        'label' => 'Ubah Kata Sandi',
        'permission' => null,
        'guard' => null,
    ],

    'organization-structure' => [
        'label' => 'Struktur Organisasi',
        'permission' => null,
        'guard' => null,
    ],

    'my-staff' => [
        'label' => 'MyStaff',
        'permission' => null,
        'guard' => 'canAccessKaryawanMenu',
    ],

    // Halaman TAMBAHAN (instruksi user 2026-08-17) - beda gate dari 'my-staff' di atas, literal
    // hasSubordinates() saja (BUKAN canAccessKaryawanMenu() yang juga meloloskan
    // canBrowseAllCompanies()) - lihat App\Livewire\Pembinaan\PembinaanAtasanPage.
    'my-staff.pembinaan' => [
        'label' => 'Coaching & Counseling',
        'permission' => null,
        'guard' => 'hasSubordinates',
    ],

    'hak-akses' => [
        'label' => 'Hak Akses',
        'permission' => \App\Enum\CustomPermissionEnum::KELOLA_HAK_AKSES->value,
        'guard' => null,
    ],

    'role' => [
        'label' => 'Role',
        'permission' => 'ViewAny:Role',
        'guard' => null,
    ],

    'karyawan.show' => [
        'label' => 'Detail Karyawan',
        'permission' => null,
        // Tidak pakai guard tunggal di sini — halaman ini dipakai 3 entry point ("Data Diri",
        // MyStaff/bawahan, Kepegawaian/company-wide). Guard per-record dicek di
        // EmployeeAttendanceStatistics::canManage(), bukan di level registry ini.
        'guard' => null,
    ],

    // Guard per-record dicek di dalam AssessmentUserForm::mount() sendiri (pola sama seperti
    // 'karyawan.show') — bisa diakses karyawan yang dinilai maupun atasan langsungnya.
    'assessment.show' => [
        'label' => 'Penilaian Karyawan',
        'permission' => null,
        'guard' => null,
    ],

    'penilaian.template' => [
        'label' => 'Template Penilaian',
        'permission' => 'ViewAny:AssessmentTemplates',
        'guard' => null,
    ],

    'penilaian.template.create' => [
        'label' => 'Tambah Template Penilaian',
        'permission' => 'Create:AssessmentTemplates',
        'guard' => null,
    ],

    'penilaian.template.edit' => [
        'label' => 'Edit Template Penilaian',
        'permission' => 'Update:AssessmentTemplates',
        'guard' => null,
    ],

    'penilaian.daftar' => [
        'label' => 'Daftar Penilaian',
        'permission' => 'ViewAny:AssessmentUsers',
        'guard' => null,
    ],

    'penilaian.daftar.tambah' => [
        'label' => 'Tambah Penilaian',
        'permission' => 'Create:AssessmentUsers',
        'guard' => null,
    ],

    'penilaian.jadwal' => [
        'label' => 'Jadwal Penilaian',
        'permission' => 'ViewAny:Assessments',
        'guard' => null,
    ],

    'penilaian.jadwal.create' => [
        'label' => 'Tambah Jadwal Penilaian',
        'permission' => 'Create:Assessments',
        'guard' => null,
    ],

    'penilaian.jadwal.edit' => [
        'label' => 'Edit Jadwal Penilaian',
        'permission' => 'Update:Assessments',
        'guard' => null,
    ],

    'kepegawaian.dashboard' => [
        'label' => 'Dashboard Kepegawaian',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.karyawan' => [
        'label' => 'Karyawan',
        'permission' => 'ViewAny:User',
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (route lolos tanpa cek middleware, gate murni di mount()) -
    // ditambahkan sekalian, sama pola migrasi Keuangan/Presensi (2026-08-07).
    'kepegawaian.karyawan.edit' => [
        'label' => 'Edit Karyawan',
        'permission' => 'Update:User',
        'guard' => null,
    ],

    'kepegawaian.karyawan.create' => [
        'label' => 'Tambah Karyawan',
        'permission' => 'Create:User',
        'guard' => null,
    ],

    'kepegawaian.karyawan.import' => [
        'label' => 'Import Akun Karyawan',
        'permission' => \App\Enum\CustomPermissionEnum::IMPORT_KARYAWAN->value,
        'guard' => null,
    ],

    'kepegawaian.karyawan.import.template' => [
        'label' => 'Template Import Akun Karyawan',
        'permission' => \App\Enum\CustomPermissionEnum::IMPORT_KARYAWAN->value,
        'guard' => null,
    ],

    'kepegawaian.dokumen' => [
        'label' => 'Dokumen Kepegawaian',
        'permission' => 'ViewAny:UserDocuments',
        'guard' => null,
    ],

    'kepegawaian.dokumen.create' => [
        'label' => 'Tambah Dokumen',
        'permission' => 'Create:UserDocuments',
        'guard' => null,
    ],

    'kepegawaian.dokumen.edit' => [
        'label' => 'Edit Dokumen',
        'permission' => 'Update:UserDocuments',
        'guard' => null,
    ],

    'kepegawaian.mutasi-karyawan' => [
        'label' => 'Perubahan Status Karyawan',
        'permission' => 'ViewAny:UserTransition',
        'guard' => null,
    ],

    'kepegawaian.mutasi-karyawan.create' => [
        'label' => 'Ajukan Perubahan Status Karyawan',
        'permission' => 'Create:UserTransition',
        'guard' => null,
    ],

    'kepegawaian.mutasi-karyawan.show' => [
        'label' => 'Detail Perubahan Status Karyawan',
        'permission' => 'View:UserTransition',
        'guard' => null,
    ],

    'kepegawaian.jabatan' => [
        'label' => 'Jabatan',
        'permission' => 'ViewAny:JobPositions',
        'guard' => null,
    ],

    'kepegawaian.jabatan.tambah' => [
        'label' => 'Ajukan Jabatan Baru',
        'permission' => 'Create:JobPositions',
        'guard' => null,
    ],

    'kepegawaian.jabatan.edit' => [
        'label' => 'Edit Jabatan',
        'permission' => 'Update:JobPositions',
        'guard' => null,
    ],

    'kepegawaian.pengaturan' => [
        'label' => 'Pengaturan Kepegawaian',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.alur-persetujuan' => [
        'label' => 'Alur Persetujuan',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value,
        'guard' => null,
    ],

    'kepegawaian.alur-persetujuan.tambah' => [
        'label' => 'Tambah Alur Persetujuan',
        'permission' => \App\Enum\CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value,
        'guard' => null,
    ],

    'kepegawaian.alur-persetujuan.edit' => [
        'label' => 'Edit Alur Persetujuan',
        'permission' => \App\Enum\CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value,
        'guard' => null,
    ],

    'kepegawaian.job-evaluation' => [
        'label' => 'Job Evaluation',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_JOB_EVALUATION->value,
        'guard' => null,
    ],

    'my-attendance-requests' => [
        'label' => 'Pengajuan Absensi',
        'permission' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
        'guard' => null,
    ],

    'my-attendance-requests.leave' => [
        'label' => 'Ajukan Cuti',
        'permission' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
        'guard' => null,
    ],

    'my-attendance-requests.permission' => [
        'label' => 'Ajukan Izin',
        'permission' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
        'guard' => null,
    ],

    'my-attendance-requests.overtime' => [
        'label' => 'Ajukan Lembur',
        'permission' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
        'guard' => null,
    ],

    'my-attendance-requests.show' => [
        'label' => 'Detail Pengajuan',
        'permission' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
        'guard' => null,
    ],

    'keuangan.dashboard' => [
        'label' => 'Dashboard Penggajian',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.periode-gaji' => [
        'label' => 'Periode Gaji',
        'permission' => 'ViewAny:PayrollPeriod',
        'guard' => null,
    ],

    'keuangan.payroll' => [
        'label' => 'Payroll',
        'permission' => 'ViewAny:Payroll',
        'guard' => null,
    ],

    'keuangan.payroll.show' => [
        'label' => 'Detail Payroll',
        'permission' => 'View:Payroll',
        'guard' => null,
    ],

    'keuangan.payroll.preview' => [
        'label' => 'Preview Slip Gaji',
        'permission' => 'View:Payroll',
        'guard' => null,
    ],

    'keuangan.komponen-gaji' => [
        'label' => 'Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value,
        'guard' => null,
    ],

    'keuangan.penerima-komponen-gaji' => [
        'label' => 'Penerima Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_PENERIMA_KOMPONEN_GAJI->value,
        'guard' => null,
    ],

    'keuangan.insentif' => [
        'label' => 'Insentif',
        'permission' => 'ViewAny:Incentive',
        'guard' => null,
    ],


    'keuangan.mapping-komponen-gaji' => [
        'label' => 'Mapping Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value,
        'guard' => null,
    ],

    'keuangan.omset' => [
        'label' => 'Omset',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_OMSET->value,
        'guard' => null,
    ],

    'keuangan.gaji-pemateri' => [
        'label' => 'Gaji Pemateri',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_GAJI_PEMATERI->value,
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (route lolos tanpa cek middleware, gate murni di mount()) -
    // ditambahkan sekalian biar konsisten & kena middleware EnsurePortalPageAccess juga.
    'keuangan.jasa-unit' => [
        'label' => 'Input Jasa Unit',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value,
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (sama seperti keuangan.jasa-unit/koperasi di atas).
    'keuangan.jasa-unit-types' => [
        'label' => 'Jasa Unit',
        'permission' => 'ViewAny:JasaUnitType',
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (sama seperti keuangan.jasa-unit di atas).
    'keuangan.koperasi' => [
        'label' => 'Koperasi',
        'permission' => 'ViewAny:UserKoperasi',
        'guard' => null,
    ],

    'keuangan.tanggungan-bpjs' => [
        'label' => 'Tanggungan BPJS',
        'permission' => 'ViewAny:BpjsDependent',
        'guard' => null,
    ],


    'presensi.dashboard' => [
        'label' => 'Dashboard Presensi',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.jadwal' => [
        'label' => 'Jadwal',
        'permission' => 'ViewAny:Schedule',
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (route lolos tanpa cek middleware, gate murni di mount()) -
    // ditambahkan sekalian, sama pola migrasi Keuangan (2026-08-07).
    'presensi.jadwal.show' => [
        'label' => 'Detail Jadwal',
        'permission' => 'View:Schedule',
        'guard' => null,
    ],

    'presensi.jadwal.edit' => [
        'label' => 'Edit Jadwal',
        'permission' => 'Update:Schedule',
        'guard' => null,
    ],

    'presensi.jadwal.pengguna' => [
        'label' => 'Penempatan Karyawan Jadwal',
        'permission' => 'Update:Schedule',
        'guard' => null,
    ],

    'presensi.jadwal.tambah' => [
        'label' => 'Tambah Jadwal',
        'permission' => 'Create:Schedule',
        'guard' => null,
    ],

    'presensi.pengaturan' => [
        'label' => 'Pengaturan Absensi',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-izin' => [
        'label' => 'Izin',
        'permission' => \App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-cuti' => [
        'label' => 'Cuti',
        'permission' => \App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-lembur' => [
        'label' => 'Lembur',
        'permission' => \App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.extra-shift' => [
        'label' => 'Extra Shift',
        'permission' => 'ViewAny:ExtraShiftAssignment',
        'guard' => null,
    ],

    'presensi.jadwal-saya' => [
        'label' => 'Jadwal Shift Saya',
        'permission' => \App\Enum\CustomPermissionEnum::JADWAL_SHIFT_SAYA->value,
        'guard' => null,
    ],

    'presensi.rekap-presensi' => [
        'label' => 'Rekap Presensi',
        'permission' => \App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.hari-libur' => [
        'label' => 'Hari Libur & Cuti Bersama',
        // Direlokasi dari 'master.hari-libur' (permission SISTEM_MASTER) ke grup Presensi &
        // Absensi, dikonfirmasi user — lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §9.2.
        // Gerbang SISTEM_ABSENSI diganti ViewAny:Holidays (2026-08-07) - HolidayResource+Policy
        // sudah ada dari awal, tinggal dipakai.
        'permission' => 'ViewAny:Holidays',
        'guard' => null,
    ],

    // Versi read-only untuk karyawan biasa (bukan halaman kelola di atas) — tidak butuh
    // permission khusus, siapa saja yang login boleh lihat. Belum ada link masuk di nav
    // manapun (dikonfirmasi user), diakses lewat URL langsung dulu — lihat §9.3.
    'presensi.kalender-libur' => [
        'label' => 'Kalender Hari Libur',
        'permission' => null,
        'guard' => null,
    ],

    'coaching.saya' => [
        'label' => 'Coaching Saya',
        'permission' => \App\Enum\CustomPermissionEnum::PEMBINAAN_SAYA->value,
        'guard' => null,
    ],

    'coaching' => [
        // Dua jalur akses beda scope (instruksi user 2026-07-26): permission Shield
        // ViewAny:Coaching (role HC khusus, company-wide) ATAU sekadar punya bawahan (scoped ke
        // bawahannya sendiri). Permission null di sini supaya middleware tidak nge-block jalur
        // kedua — gate & scope sesungguhnya ada di CoachingPage/CreateCoachingPage/
        // CoachingDetailPage.
        'label' => 'Coaching',
        'permission' => null,
        'guard' => null,
    ],

    'coaching.create' => [
        'label' => 'Buat Coaching',
        'permission' => null,
        'guard' => null,
    ],

    'coaching.show' => [
        'label' => 'Detail Coaching',
        'permission' => null,
        'guard' => null,
    ],

    'counseling' => [
        'label' => 'Counseling',
        'permission' => \App\Enum\CustomPermissionEnum::PEMBINAAN_SAYA->value,
        'guard' => null,
    ],

    'counseling.kelola' => [
        // Bukan lagi gate permission Shield 'ViewAny:Counseling' company-wide — konselor bisa
        // siapa saja (karyawan menunjuk sendiri saat konsul), gate sesungguhnya ada di dalam
        // CounselingManagePage/CounselingDetailPage (counselor_id === auth()->id()). Permission
        // null di sini supaya middleware tidak nge-block counselor yang tidak punya permission
        // itu — lihat instruksi user 2026-07-26.
        'label' => 'Counseling',
        'permission' => null,
        'guard' => null,
    ],

    'counseling.kelola.show' => [
        'label' => 'Detail Counseling',
        'permission' => null,
        'guard' => null,
    ],

    'tugas' => [
        'label' => 'Tugas',
        'permission' => \App\Enum\CustomPermissionEnum::TUGAS_SAYA->value,
        'guard' => null,
    ],

    'tugas.pengaturan' => [
        'label' => 'Pengaturan Tugas',
        'permission' => \App\Enum\CustomPermissionEnum::EDIT_PENGATURAN_TUGAS->value,
        'guard' => null,
    ],

    'pelatihan' => [
        'label' => 'Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::PELATIHAN_SAYA->value,
        'guard' => null,
    ],

    'pelatihan.honorarium' => [
        'label' => 'Honorarium',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_HONORARIUM_LMS->value,
        'guard' => null,
    ],

    'pelatihan.kategori' => [
        'label' => 'Kategori Pelatihan',
        'permission' => 'ViewAny:LmsCategories',
        'guard' => null,
    ],

    // Baseline Pelatihan — padanan portal dari Filament LmsCourseBaselinesResource. SISTEM_LMS
    // gerbang akses halaman, per-aksi Create/Update/Delete dicek via Policy Shield
    // (LmsCourseBaselinesPolicy) langsung di dalam LmsCourseBaselinesPage.
    'pelatihan.baseline' => [
        'label' => 'Baseline Pelatihan',
        'permission' => 'ViewAny:LmsCourseBaselines',
        'guard' => null,
    ],

    'pelatihan.evaluasi' => [
        'label' => 'Pertanyaan Evaluasi',
        'permission' => 'ViewAny:LmsFeedbackQuestion',
        'guard' => null,
    ],

    // Belum terdaftar sebelumnya (route lolos tanpa cek middleware, gate murni di mount()) -
    // ditambahkan sekalian, sama pola migrasi modul lain (2026-08-07).
    'pelatihan.pengaturan' => [
        'label' => 'Pengaturan LMS',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_LMS->value,
        'guard' => null,
    ],

    // Laporan Pelatihan — padanan portal dari Filament TrainingReportResource. Halaman
    // read-only (filter + export Excel), tidak ada Create/Update/Delete jadi cukup SISTEM_LMS.
    'pelatihan.laporan' => [
        'label' => 'Laporan Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::LIHAT_LAPORAN_PELATIHAN->value,
        'guard' => null,
    ],

    // Bank Soal — padanan portal dari Filament QuestionBanksResource. SISTEM_LMS gerbang akses
    // halaman (baca list), per-aksi Create/Update/Delete:LmsQuestionBanks dicek terpisah di
    // dalam BankSoalPage/CreateBankSoal (Policy Shield, format sudah di-rename dari 'QuestionBanks'
    // → 'LmsQuestionBanks' via migration 2026_07_14_150000_rename_question_banks_permissions).
    'pelatihan.bank-soal' => [
        'label' => 'Bank Soal',
        'permission' => 'ViewAny:LmsQuestionBanks',
        'guard' => null,
    ],

    'pelatihan.bank-soal.create' => [
        'label' => 'Tambah Bank Soal',
        'permission' => 'Create:LmsQuestionBanks',
        'guard' => null,
    ],

    // Kuis — padanan portal dari Filament QuizzesResource. Otorisasi CRUD sesungguhnya lewat
    // Policy Shield (ViewAny/Create/Update/Delete:LmsQuizzes) di dalam komponennya sendiri, sama
    // seperti Bank Soal — 'permission' di sini cuma gate akses halaman.
    'pelatihan.kuis' => [
        'label' => 'Kuis',
        'permission' => 'ViewAny:LmsQuizzes',
        'guard' => null,
    ],

    'pelatihan.kuis.create' => [
        'label' => 'Buat Kuis',
        'permission' => 'Create:LmsQuizzes',
        'guard' => null,
    ],

    'pelatihan.kuis.edit' => [
        'label' => 'Ubah Kuis',
        'permission' => 'Update:LmsQuizzes',
        'guard' => null,
    ],

    // Otorisasi CRUD sesungguhnya lewat Policy Shield (ViewAny/Create/Update/Delete:Trainers,
    // lihat App\Filament\Resources\LMS\Trainers\TrainersResource) — SISTEM_LMS di sini cuma
    // gerbang akses baca halamannya, sama pola dengan pelatihan.kategori/pelatihan.kelola.
    'pelatihan.pemateri' => [
        'label' => 'Pemateri',
        'permission' => 'ViewAny:Trainers',
        'guard' => null,
    ],

    'pelatihan.pemateri.show' => [
        'label' => 'Detail Pemateri',
        'permission' => 'View:Trainers',
        'guard' => null,
    ],

    'pelatihan.kelola' => [
        'label' => 'Pelatihan',
        'permission' => 'ViewAny:LmsCourses',
        'guard' => null,
    ],

    'pelatihan.kelola.create' => [
        'label' => 'Tambah Pelatihan',
        'permission' => 'Create:LmsCourses',
        'guard' => null,
    ],

    'pelatihan.kelola.show' => [
        'label' => 'Detail Pelatihan',
        'permission' => 'View:LmsCourses',
        'guard' => null,
    ],

    'pelatihan.show' => [
        'label' => 'Detail Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::PELATIHAN_SAYA->value,
        'guard' => null,
    ],

    // Gate approval per-record (bukan permission generik) ditegakkan di dalam
    // App\Livewire\Pelatihan\QuizAttempt sendiri, bukan lewat registry ini.
    'pelatihan.quiz' => [
        'label' => 'Quiz',
        'permission' => null,
        'guard' => null,
    ],

    // Gate approval per-record (enrollment, lock) ditegakkan di dalam
    // App\Livewire\Pelatihan\CourseFeedback sendiri, bukan lewat registry ini.
    'pelatihan.feedback' => [
        'label' => 'Umpan Balik',
        'permission' => null,
        'guard' => null,
    ],

    'kepegawaian.lowongan' => [
        'label' => 'Lowongan Kerja',
        'permission' => 'ViewAny:JobOpenings',
        'guard' => null,
    ],

    'kepegawaian.lowongan.edit' => [
        'label' => 'Edit Lowongan Kerja',
        'permission' => 'Update:JobOpenings',
        'guard' => null,
    ],

    'kepegawaian.lowongan.show' => [
        'label' => 'Detail Lowongan Kerja',
        'permission' => 'View:JobOpenings',
        'guard' => null,
    ],

    'kepegawaian.lowongan.kandidat' => [
        'label' => 'Detail Kandidat',
        'permission' => 'View:JobOpenings',
        'guard' => null,
    ],

    'kepegawaian.permintaan' => [
        'label' => 'Permintaan Tenaga Kerja',
        'permission' => 'ViewAny:ManpowerRequests',
        'guard' => null,
    ],

    'kepegawaian.permintaan.create' => [
        'label' => 'Ajukan Permintaan Tenaga Kerja',
        'permission' => 'Create:ManpowerRequests',
        'guard' => null,
    ],

    'kepegawaian.permintaan.show' => [
        'label' => 'Detail Permintaan Tenaga Kerja',
        'permission' => 'View:ManpowerRequests',
        'guard' => null,
    ],

];
