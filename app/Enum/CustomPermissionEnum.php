<?php

namespace App\Enum;

/**
 * Permission "manual" (bukan format Shield Verb:Subject, tidak disentuh shield:generate) —
 * dipakai sebagai gerbang akses global ke suatu fitur (bukan permission CRUD per-Resource
 * biasa). Baris nilainya di database dibuat/dijaga lewat
 * database/seeders/CustomPermissionsSeeder.php — kalau nambah case baru di sini, tambahkan
 * juga ke $permissions di seeder itu lalu jalankan ulang:
 *   php artisan db:seed --class=CustomPermissionsSeeder
 */
enum CustomPermissionEnum: string
{
    case SISTEM_KEUANGAN = 'sistem_keuangan';
    case SISTEM_MASTER = 'sistem_master';
    case SISTEM_ABSENSI = 'sistem_absensi';
    case SISTEM_KEPEGAWAIAN = 'sistem_kepegawaian';
    case SISTEM_LMS = 'sistem_lms';
    case SISTEM_TUGAS = 'sistem_tugas';
    case SISTEM_REKRUTMEN = 'sistem_rekrutmen';
    case SISTEM_COACHING_COUNSELING = 'sistem_coaching_counseling';
    case PELATIHAN_SAYA = 'pelatihan_saya';
    case PENGAJUAN_ABSENSI_SAYA = 'pengajuan_absensi_saya';
    case COACHING_COUNSELING_SAYA = 'coaching_counseling_saya';

    // Verb non-CRUD (approve/import/report) - dideklarasikan juga di
    // config/filament-shield.php 'custom_permissions' supaya Shield tidak menganggapnya yatim.
    // Sebelumnya dijaga lewat PermissionsSeeder.php lama (sudah dihapus), dipindah ke sini.
    case APPROVE_PENGAJUAN_ABSENSI = 'approve_pengajuan_absensi';
    case VIEW_REPORT_DATA_ABSENSI = 'view_report_data_absensi';
    case IMPORT_DATA_ABSENSI = 'import_data_absensi';
    case APPROVE_PENGAJUAN_PELATIHAN = 'approve_pengajuan_pelatihan';
    case APPROVE_MANAJEMEN_LOWONGAN_KERJA = 'approve_manajemen_lowongan_kerja';
    case LIHAT_PERUSAHAAN_LAIN = 'lihat_perusahaan_lain';

    public function label(): string
    {
        return match
         ($this) {
            self::SISTEM_KEUANGAN => 'Sistem Keuangan',
            self::SISTEM_MASTER => 'Sistem Master',
            self::SISTEM_ABSENSI => 'Sistem Absensi',
            self::SISTEM_KEPEGAWAIAN => 'Sistem Kepegawaian',
            self::SISTEM_LMS => 'Sistem LMS',
            self::SISTEM_TUGAS => 'Sistem Tugas',
            self::SISTEM_REKRUTMEN => 'Sistem Rekrutmen',
            self::SISTEM_COACHING_COUNSELING => 'Sistem Coaching & Counseling',
            self::PELATIHAN_SAYA => 'Pelatihan Saya',
            self::PENGAJUAN_ABSENSI_SAYA => 'Pengajuan Absensi Saya',
            self::COACHING_COUNSELING_SAYA => 'Coaching & Counseling Saya',
            self::APPROVE_PENGAJUAN_ABSENSI => 'Approve Pengajuan Absensi',
            self::VIEW_REPORT_DATA_ABSENSI => 'Lihat Laporan Data Absensi',
            self::IMPORT_DATA_ABSENSI => 'Import Data Absensi',
            self::APPROVE_PENGAJUAN_PELATIHAN => 'Approve Pengajuan Pelatihan',
            self::APPROVE_MANAJEMEN_LOWONGAN_KERJA => 'Approve Manajemen Lowongan Kerja',
            self::LIHAT_PERUSAHAAN_LAIN => 'Lihat Perusahaan Lain',
        };
    }
}
