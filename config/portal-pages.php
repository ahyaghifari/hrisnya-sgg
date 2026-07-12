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
        'label' => 'Dashboard',
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

    'my-staff.show' => [
        'label' => 'Detail Karyawan',
        'permission' => null,
        // Tidak pakai 'canAccessKaryawanMenu' di sini — halaman ini sekarang dipakai juga
        // buat "Data Diri" (lihat data sendiri, tombol di Dashboard), bukan cuma atasan
        // lihat bawahan. Guard per-record (bawahan ATAU diri sendiri) dicek di
        // EmployeeAttendanceStatistics::canManage(), bukan di level registry ini.
        'guard' => null,
    ],

    'my-attendance-requests' => [
        'label' => 'Pengajuan Absensi',
        'permission' => null,
        'guard' => null,
    ],

    'my-attendance-requests.leave' => [
        'label' => 'Ajukan Cuti',
        'permission' => null,
        'guard' => null,
    ],

    'my-attendance-requests.permission' => [
        'label' => 'Ajukan Izin',
        'permission' => null,
        'guard' => null,
    ],

    'my-attendance-requests.overtime' => [
        'label' => 'Ajukan Lembur',
        'permission' => null,
        'guard' => null,
    ],

    'my-attendance-requests.show' => [
        'label' => 'Detail Pengajuan',
        'permission' => null,
        'guard' => null,
    ],

    'keuangan.komponen-gaji' => [
        'label' => 'Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.mapping-komponen-gaji' => [
        'label' => 'Mapping Komponen Gaji',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],

    'keuangan.insentif' => [
        'label' => 'Insentif',
        'permission' => \App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value,
        'guard' => null,
    ],
];
