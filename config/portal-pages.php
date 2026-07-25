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

    'my-staff.edit' => [
        'label' => 'Edit Karyawan',
        'permission' => null,
        'guard' => 'canAccessKaryawanMenu',
    ],

    'karyawan.show' => [
        'label' => 'Detail Karyawan',
        'permission' => null,
        // Tidak pakai guard tunggal di sini — halaman ini dipakai 3 entry point ("Data Diri",
        // MyStaff/bawahan, Kepegawaian/company-wide). Guard per-record dicek di
        // EmployeeAttendanceStatistics::canManage(), bukan di level registry ini.
        'guard' => null,
    ],

    'kepegawaian.dashboard' => [
        'label' => 'Dashboard Kepegawaian',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.karyawan' => [
        'label' => 'Karyawan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.karyawan.create' => [
        'label' => 'Tambah Karyawan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.dokumen' => [
        'label' => 'Dokumen Kepegawaian',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.dokumen.create' => [
        'label' => 'Tambah Dokumen',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        'guard' => null,
    ],

    'kepegawaian.dokumen.edit' => [
        'label' => 'Edit Dokumen',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
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
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.payroll' => [
        'label' => 'Payroll',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.payroll.show' => [
        'label' => 'Detail Payroll',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.payroll.preview' => [
        'label' => 'Preview Slip Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.komponen-gaji' => [
        'label' => 'Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.penerima-komponen-gaji' => [
        'label' => 'Penerima Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.insentif' => [
        'label' => 'Insentif',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],


    'keuangan.mapping-komponen-gaji' => [
        'label' => 'Mapping Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'presensi.dashboard' => [
        'label' => 'Dashboard Presensi',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.jadwal' => [
        'label' => 'Jadwal',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.jadwal.tambah' => [
        'label' => 'Tambah Jadwal',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.pengaturan' => [
        'label' => 'Pengaturan Absensi',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-izin' => [
        'label' => 'Izin',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-cuti' => [
        'label' => 'Cuti',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.rekap-lembur' => [
        'label' => 'Lembur',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
        'guard' => null,
    ],

    'presensi.hari-libur' => [
        'label' => 'Hari Libur & Cuti Bersama',
        // Direlokasi dari 'master.hari-libur' (permission SISTEM_MASTER) ke grup Presensi &
        // Absensi, dikonfirmasi user — lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §9.2.
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value,
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
        'permission' => \App\Enum\CustomPermissionEnum::COACHING_COUNSELING_SAYA->value,
        'guard' => null,
    ],

    'coaching' => [
        'label' => 'Coaching',
        // Murni permission Shield (bukan guard hasSubordinates() seperti 'my-staff') — siapa
        // yang bisa kelola Coaching ditentukan lewat checklist role di /admin/roles (1 role
        // khusus per company, company-wide, bukan scoped subordinates/departemen). Lihat
        // planning/2026-07-18-portal-coaching-counseling.md §3.2.
        'permission' => 'ViewAny:Coaching',
        'guard' => null,
    ],

    'coaching.create' => [
        'label' => 'Buat Coaching',
        'permission' => 'Create:Coaching',
        'guard' => null,
    ],

    'coaching.show' => [
        'label' => 'Detail Coaching',
        'permission' => 'View:Coaching',
        'guard' => null,
    ],

    'counseling' => [
        'label' => 'Counseling',
        'permission' => \App\Enum\CustomPermissionEnum::COACHING_COUNSELING_SAYA->value,
        'guard' => null,
    ],

    'counseling.kelola' => [
        'label' => 'Kelola Counseling',
        'permission' => 'ViewAny:Counseling',
        'guard' => null,
    ],

    'counseling.kelola.show' => [
        'label' => 'Detail Counseling',
        'permission' => 'View:Counseling',
        'guard' => null,
    ],

    'tugas' => [
        'label' => 'Tugas',
        'permission' => null,
        'guard' => null,
    ],

    'tugas.pengaturan' => [
        'label' => 'Pengaturan Tugas',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_TUGAS->value,
        'guard' => null,
    ],

    'pelatihan' => [
        'label' => 'Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::PELATIHAN_SAYA->value,
        'guard' => null,
    ],

    'pelatihan.honorarium' => [
        'label' => 'Honorarium',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value,
        'guard' => null,
    ],

    'pelatihan.kategori' => [
        'label' => 'Kategori Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value,
        'guard' => null,
    ],

    'pelatihan.kelola' => [
        'label' => 'Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value,
        'guard' => null,
    ],

    'pelatihan.kelola.create' => [
        'label' => 'Tambah Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value,
        'guard' => null,
    ],

    'pelatihan.kelola.show' => [
        'label' => 'Detail Pelatihan',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value,
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

    'rekrutmen.lowongan' => [
        'label' => 'Lowongan Kerja',
        'permission' => 'ViewAny:JobOpenings',
        'guard' => null,
    ],

    'rekrutmen.lowongan.edit' => [
        'label' => 'Edit Lowongan Kerja',
        'permission' => 'Update:JobOpenings',
        'guard' => null,
    ],

    'rekrutmen.lowongan.show' => [
        'label' => 'Detail Lowongan Kerja',
        'permission' => 'View:JobOpenings',
        'guard' => null,
    ],

    'rekrutmen.lowongan.kandidat' => [
        'label' => 'Detail Kandidat',
        'permission' => 'View:JobOpenings',
        'guard' => null,
    ],

    'rekrutmen.permintaan-tenaga-kerja' => [
        'label' => 'Permintaan Tenaga Kerja',
        'permission' => 'ViewAny:ManpowerRequests',
        'guard' => null,
    ],

    'rekrutmen.permintaan-tenaga-kerja.create' => [
        'label' => 'Ajukan Permintaan Tenaga Kerja',
        'permission' => 'Create:ManpowerRequests',
        'guard' => null,
    ],

    'rekrutmen.permintaan-tenaga-kerja.show' => [
        'label' => 'Detail Permintaan Tenaga Kerja',
        'permission' => 'View:ManpowerRequests',
        'guard' => null,
    ],

];
