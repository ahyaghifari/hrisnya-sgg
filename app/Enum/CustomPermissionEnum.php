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
    case SISTEM_COACHING_COUNSELING = 'sistem_coaching_counseling';
    case PELATIHAN_SAYA = 'pelatihan_saya';
    case PENGAJUAN_ABSENSI_SAYA = 'pengajuan_absensi_saya';
    case COACHING_COUNSELING_SAYA = 'coaching_counseling_saya';

    /** Beda dari sekadar "punya bawahan" (CoachingPage/CreateCoachingPage/CoachingDetailPage
     * default scope ke OrgHierarchyService::subordinates()) — permission ini kasih akses
     * mengcoaching SEMUA karyawan company tanpa terkecuali, dipakai buat role seperti HC yang
     * bukan atasan langsung siapa-siapa di struktur organisasi tapi tetap perlu bisa coaching
     * siapa saja. */
    case COACHING_KARYAWAN_LAINNYA = 'coaching_karyawan_lainnya';

    // Verb non-CRUD (approve/import/report) - dideklarasikan juga di
    // config/filament-shield.php 'custom_permissions' supaya Shield tidak menganggapnya yatim.
    // Sebelumnya dijaga lewat PermissionsSeeder.php lama (sudah dihapus), dipindah ke sini.
    case APPROVE_PENGAJUAN_ABSENSI = 'approve_pengajuan_absensi';
    case VIEW_REPORT_DATA_ABSENSI = 'view_report_data_absensi';
    case IMPORT_DATA_ABSENSI = 'import_data_absensi';
    case APPROVE_PENGAJUAN_PELATIHAN = 'approve_pengajuan_pelatihan';
    case APPROVE_MANAJEMEN_LOWONGAN_KERJA = 'approve_manajemen_lowongan_kerja';
    case LIHAT_PERUSAHAAN_LAIN = 'lihat_perusahaan_lain';

    // Gerbang EDIT untuk halaman portal yang modelnya tidak punya Filament Resource/Policy
    // sendiri (config singleton, mapping, dsb) — mount() halamannya cuma cek SISTEM_* (akses
    // baca), permission di bawah ini yang membedakan siapa yang boleh menyimpan/menghapus.
    case EDIT_MAPPING_KOMPONEN_GAJI = 'edit_mapping_komponen_gaji';
    case EDIT_PENERIMA_KOMPONEN_GAJI = 'edit_penerima_komponen_gaji';
    case EDIT_KOMPONEN_GAJI = 'edit_komponen_gaji';
    case EDIT_KOMPONEN_GAJI_GLOBAL = 'edit_komponen_gaji_global';
    case EDIT_DETAIL_KURSUS_LMS = 'edit_detail_kursus_lms';
    case EDIT_HONORARIUM_LMS = 'edit_honorarium_lms';
    case EDIT_PENGATURAN_ABSENSI = 'edit_pengaturan_absensi';
    case SYNC_DATA_ABSENSI = 'sync_data_absensi';
    case EDIT_PENGATURAN_TUGAS = 'edit_pengaturan_tugas';
    case EDIT_PENGATURAN_LMS = 'edit_pengaturan_lms';

    /**
     * Beda dari Update:Schedule (edit header jadwal, ubah karyawan yang dipasangkan) — ini
     * khusus buat karu/kepala ruangan yang cuma perlu kelola Template Rotasi + generate +
     * detail penjadwalan (roster) SATU jadwal shift yang sudah ada, tanpa perlu bisa ubah
     * jadwal/pengguna-nya. Cuma berlaku di tab "Template Shift" & "Detail Penjadwalan"
     * (ScheduleShiftRotationTab & ScheduleShiftRosterTab) — keduanya cuma tampil untuk jadwal
     * bertipe Shift.
     */
    case KELOLA_JADWAL_SHIFT = 'kelola_jadwal_shift';

    /** Generate/regenerate payroll (PayrollPeriodsPage) — aksi berat/sensitif (nulis ratusan
     * baris payroll sekaligus), sengaja dipisah dari Update:PayrollPeriod (yang cuma ubah
     * tanggal periode) supaya bisa dikasih ke role finance yang lebih terbatas. */
    case GENERATE_PAYROLL = 'generate_payroll';

    /** Lihat lampiran KTP/KK karyawan lain di halaman Data Diri (kartu "Lampiran Identitas") —
     * dipisah dari akses baca Data Diri secara umum (atasan/HC yang biasa buka Data Diri
     * bawahannya TIDAK otomatis bisa lihat lampiran ini kecuali dikasih permission ini secara
     * eksplisit). Pemilik akun selalu bisa lihat/unggah lampirannya sendiri, tanpa permission ini. */
    case LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN = 'lihat_lampiran_identitas_karyawan';

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
            self::SISTEM_COACHING_COUNSELING => 'Sistem Coaching & Counseling',
            self::PELATIHAN_SAYA => 'Pelatihan Saya',
            self::PENGAJUAN_ABSENSI_SAYA => 'Pengajuan Absensi Saya',
            self::COACHING_COUNSELING_SAYA => 'Coaching & Counseling Saya',
            self::COACHING_KARYAWAN_LAINNYA => 'Coaching Karyawan Lainnya (Semua Karyawan, Bukan Cuma Bawahan)',
            self::APPROVE_PENGAJUAN_ABSENSI => 'Approve Pengajuan Absensi',
            self::VIEW_REPORT_DATA_ABSENSI => 'Lihat Laporan Data Absensi',
            self::IMPORT_DATA_ABSENSI => 'Import Data Absensi',
            self::APPROVE_PENGAJUAN_PELATIHAN => 'Approve Pengajuan Pelatihan',
            self::APPROVE_MANAJEMEN_LOWONGAN_KERJA => 'Approve Manajemen Lowongan Kerja',
            self::LIHAT_PERUSAHAAN_LAIN => 'Lihat Perusahaan Lain',
            self::EDIT_MAPPING_KOMPONEN_GAJI => 'Edit Mapping Komponen Gaji',
            self::EDIT_PENERIMA_KOMPONEN_GAJI => 'Edit Penerima Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI => 'Edit Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI_GLOBAL => 'Edit Pengaturan Global Komponen Gaji',
            self::EDIT_DETAIL_KURSUS_LMS => 'Edit Detail Kursus LMS',
            self::EDIT_HONORARIUM_LMS => 'Edit Honorarium LMS',
            self::EDIT_PENGATURAN_ABSENSI => 'Edit Pengaturan Absensi',
            self::SYNC_DATA_ABSENSI => 'Sync Data Absensi (dari Access Logs)',
            self::EDIT_PENGATURAN_TUGAS => 'Edit Pengaturan Tugas',
            self::EDIT_PENGATURAN_LMS => 'Edit Pengaturan LMS',
            self::KELOLA_JADWAL_SHIFT => 'Kelola Jadwal Shift (Template & Roster)',
            self::GENERATE_PAYROLL => 'Generate/Regenerate Payroll',
            self::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN => 'Lihat Lampiran Identitas Karyawan (KTP/KK)',
        };
    }
}
