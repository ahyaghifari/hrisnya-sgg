<?php

namespace App\Enum;

/**
 * Permission "manual" (bukan format Shield Verb:Subject, tidak disentuh shield:generate) —
 * dipakai sebagai gerbang akses global ke suatu fitur (bukan permission CRUD per-Resource
 * biasa). Baris nilainya di database dibuat/dijaga lewat
 * database/seeders/CustomPermissionsSeeder.php — kalau nambah case baru di sini, tambahkan
 * juga ke $permissions di seederitu lalu jalankan ulang:
 *   php artisan db:seed --class=CustomPermissionsSeeder
 */
enum CustomPermissionEnum: string
{
    case SISTEM_KEUANGAN = 'sistem_keuangan';
    case SISTEM_ABSENSI = 'sistem_absensi';
    case SISTEM_KEPEGAWAIAN = 'sistem_kepegawaian';
    case SISTEM_LMS = 'sistem_lms';
    case SISTEM_PEMBINAAN_KARYAWAN = 'sistem_pembinaan_karyawan';
    case SISTEM_PENILAIAN_KARYAWAN = 'sistem_penilaian_karyawan';

    /** Gerbang portal buat permissio approve lintas-modul (APPROVE_PENGAJUAN_ABSENSI,
     * APPROVE_MANAJEMEN_LOWONGAN_KERJA) yang tidak numpang di gerbang SISTEM_* modulnya
     * masing-masing (2026-08-08, permintaan user - dipisah biar approval bisa didelegasikan
     * ke role yang bukan pemegang akses penuh modul terkait). */
    case SISTEM_APPROVAL = 'sistem_approval';
    case TUGAS_SAYA = 'tugas_saya';
    case PELATIHAN_SAYA = 'pelatihan_saya';
    case AJUKAN_PELATIHAN = 'ajukan_pelatihan';
    case PENGAJUAN_ABSENSI_SAYA = 'pengajuan_absensi_saya';
    case PEMBINAAN_SAYA = 'pembinaan_saya';

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

    /** Import akun dasar karyawan (nama/email/nip saja, lewat halaman
     * /kepegawaian/karyawan/import) - beda dari Create:User (buat 1 akun manual lewat form)
     * karena ini operasi massal dengan preview Baru/Berubah/Tidak Ada di File sebelum submit. */
    case IMPORT_KARYAWAN = 'import_karyawan';

    // Gerbang EDIT untuk halaman portal yang modelnya tidak punya Filament Resource/Policy
    // sendiri (config singleton, mapping, dsb) — mount() halamannya cuma cek SISTEM_* (akses
    // baca), permission di bawah ini yang membedakan siapa yang boleh menyimpan/menghapus.
    case EDIT_MAPPING_KOMPONEN_GAJI = 'edit_mapping_komponen_gaji';
    case EDIT_PENERIMA_KOMPONEN_GAJI = 'edit_penerima_komponen_gaji';
    case EDIT_KOMPONEN_GAJI = 'edit_komponen_gaji';
    case EDIT_KOMPONEN_GAJI_GLOBAL = 'edit_komponen_gaji_global';

    /** Gerbang LIHAT (mount()) buat 3 halaman di atas - dulu numpang SISTEM_KEUANGAN, sekarang
     * punya permission sendiri, pasangan dari EDIT_KOMPONEN_GAJI/EDIT_PENERIMA_KOMPONEN_GAJI/
     * EDIT_MAPPING_KOMPONEN_GAJI yang sudah lebih dulu ada. */
    case LIHAT_KOMPONEN_GAJI = 'lihat_komponen_gaji';
    case LIHAT_PENERIMA_KOMPONEN_GAJI = 'lihat_penerima_komponen_gaji';
    case LIHAT_MAPPING_KOMPONEN_GAJI = 'lihat_mapping_komponen_gaji';

    /** Halaman Omset (`/keuangan/omset`, rincian omset per kategori per periode gaji, dijumlah
     * jadi Total Omset dipakai JasaPelayananCalculator) — model-nya (OmsetDetail/OmsetCategory)
     * tidak punya Filament Resource/Policy sendiri, jadi 2 permission manual: LIHAT_OMSET
     * gerbang mount() halaman (dulu SISTEM_KEUANGAN), KELOLA_OMSET gerbang simpan (dulu numpang
     * EDIT_MAPPING_KOMPONEN_GAJI, sekarang dipisah punya sendiri). */
    case LIHAT_OMSET = 'lihat_omset';
    case KELOLA_OMSET = 'kelola_omset';

    /** Halaman Input Jasa Unit (`/keuangan/jasa-unit`, input nominal jasa unit per org
     * structure/karyawan per periode gaji) — sama pola dengan LIHAT_OMSET/KELOLA_OMSET: model-nya
     * tidak punya Filament Resource/Policy sendiri, dulu numpang SISTEM_KEUANGAN (lihat) +
     * EDIT_MAPPING_KOMPONEN_GAJI (kelola), sekarang punya permission sendiri. */
    case LIHAT_INPUT_JASA_UNIT = 'lihat_input_jasa_unit';
    case KELOLA_INPUT_JASA_UNIT = 'kelola_input_jasa_unit';

    /** Halaman Job Evaluation (`/kepegawaian/job-evaluation`, edit job_evaluation_score per
     * Jabatan, dipakai JasaPelayananCalculator sebagai pembagi rasio Jasa Pelayanan) - murni
     * konfigurasi, JobPositions sendiri sudah punya Resource+Policy (ViewAny/Update:JobPositions)
     * tapi itu buat CRUD Jabatan, bukan buat skor JE-nya - dipisah permission sendiri, sama
     * pola LIHAT_OMSET/KELOLA_OMSET. Dulu numpang SISTEM_KEPEGAWAIAN buat lihat DAN simpan
     * (saveJe() sebelumnya malah tanpa cek apapun). */
    case LIHAT_JOB_EVALUATION = 'lihat_job_evaluation';
    case KELOLA_JOB_EVALUATION = 'kelola_job_evaluation';

    /** Tambah/ubah/hapus baris penyesuaian manual di payroll SATU karyawan (baris Bon di
     * PayrollDetailPage, "Add Adjustment" di Filament PayrollItemsRelationManager) — dipisah
     * dari Update:Payroll (yang juga dipakai markAsPaid/regenerate/dst) supaya bisa dikasih ke
     * lebih sedikit orang. Tetap butuh Update:Payroll juga (dicek dulu sebelum ini) - permission
     * ini mempersempit, bukan pengganti. */
    case EDIT_PAYROLL_ADJUSTMENT = 'edit_payroll_adjustment';
    case EDIT_DETAIL_KURSUS_LMS = 'edit_detail_kursus_lms';

    /** Buka halaman Master Honorarium (read-only) - beda dari EDIT_HONORARIUM_LMS yang
     * menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali. */
    case LIHAT_HONORARIUM_LMS = 'lihat_honorarium_lms';
    case EDIT_HONORARIUM_LMS = 'edit_honorarium_lms';

    /** Buka halaman Laporan Pelatihan (`/lms/laporan`) - laporan read-only murni, tidak ada
     * gerbang EDIT pasangannya (tidak ada aksi tulis di halaman ini). */
    case LIHAT_LAPORAN_PELATIHAN = 'lihat_laporan_pelatihan';
    case EDIT_PENGATURAN_ABSENSI = 'edit_pengaturan_absensi';

    /** Buka halaman Pengaturan Absensi (read-only) - beda dari EDIT_PENGATURAN_ABSENSI yang
     * menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali (bukan cuma
     * disembunyikan tombol simpannya) - dipisah supaya role yang cuma perlu approve/lihat
     * absensi (sistem_absensi) tidak otomatis bisa buka pengaturan cutoff/kuota perusahaan. */
    case LIHAT_PENGATURAN_ABSENSI = 'lihat_pengaturan_absensi';
    case SYNC_DATA_ABSENSI = 'sync_data_absensi';
    case EDIT_PENGATURAN_TUGAS = 'edit_pengaturan_tugas';
    case EDIT_PENGATURAN_LMS = 'edit_pengaturan_lms';
    case EDIT_PENGATURAN_KEPEGAWAIAN = 'edit_pengaturan_kepegawaian';

    /** Buka halaman Pengaturan Kepegawaian (read-only) - beda dari EDIT_PENGATURAN_KEPEGAWAIAN
     * yang menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali (bukan cuma
     * disembunyikan tombol simpan/input-nya), sama seperti pola LIHAT_PENGATURAN_ABSENSI. */
    case LIHAT_PENGATURAN_KEPEGAWAIAN = 'lihat_pengaturan_kepegawaian';

    /** Buka halaman Pengaturan LMS (read-only) - beda dari EDIT_PENGATURAN_LMS yang
     * menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali, sama pola
     * LIHAT_PENGATURAN_ABSENSI/LIHAT_PENGATURAN_KEPEGAWAIAN. */
    case LIHAT_PENGATURAN_LMS = 'lihat_pengaturan_lms';

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

    /** Gerbang halaman portal Hak Akses (`/hak-akses`, tree permission per role) — buka
     * halaman, pilih company/role, centang/simpan permission. Bukan Update:Role (Shield CRUD
     * biasa) supaya bisa dikasih ke role terbatas tanpa otomatis dapat semua akses Role
     * Resource lain di Filament admin. */
    case KELOLA_HAK_AKSES = 'kelola_hak_akses';

    /** Halaman portal Gaji Pemateri (`/keuangan/gaji-pemateri`, rekap honor pemateri per
     * pelatihan dari TrainerAssessmentResults) — link nav berdiri sendiri (bukan dalam dropdown
     * apapun, sama seperti "Riwayat Approval"), sengaja permission manual sendiri (bukan reuse
     * ViewAny/Delete:TrainerAssessmentResults milik admin Filament) supaya akses portal bisa
     * diatur terpisah dari akses admin, sama pola LIHAT_OMSET/KELOLA_OMSET. */
    case LIHAT_GAJI_PEMATERI = 'lihat_gaji_pemateri';
    case KELOLA_GAJI_PEMATERI = 'kelola_gaji_pemateri';

    /** Gerbang MASUK ke admin panel Filament (`/admin`) buat role SELAIN "Admin"/"Super Admin"
     * literal — User::canAccessPanel() cek nama role dulu (jalur utama, hampir semua company
     * admin lewat sini), permission ini cuma jalur kedua buat kasus khusus (mis. IT Support
     * yang butuh masuk /admin tanpa dijadikan role "Admin" penuh). TIDAK otomatis dikasih ke
     * siapa-siapa - role non-Admin/Super-Admin yang belum eksplisit dikasih ini TIDAK bisa
     * masuk /admin sama sekali (2026-08-08: sebelumnya canAccessPanel() `return true` tanpa
     * syarat, siapa saja yang login bisa buka /admin - celah ini yang ditutup). */
    case AKSES_PANEL_ADMIN = 'akses_panel_admin';

    /** Gerbang halaman portal "Jadwal Shift Saya" (`/presensi/jadwal-saya`, kalender bulanan
     * jadwal pribadi user yang login) + kartu ringkasannya di Dashboard - pola sama
     * PENGAJUAN_ABSENSI_SAYA/PELATIHAN_SAYA (permission self-service, bukan default-on untuk
     * semua role seperti 3 itu - permission ini BARU, jadi tetap off sampai di-assign manual
     * lewat /hak-akses, tidak di-grandfather ke role manapun). */
    case JADWAL_SHIFT_SAYA = 'jadwal_shift_saya';

    public function label(): string
    {
        return match
         ($this) {
            self::SISTEM_KEUANGAN => 'Sistem Keuangan',
            self::SISTEM_ABSENSI => 'Sistem Absensi',
            self::SISTEM_KEPEGAWAIAN => 'Sistem Kepegawaian',
            self::SISTEM_LMS => 'Sistem LMS',
            self::SISTEM_PEMBINAAN_KARYAWAN => 'Sistem Pembinaan Karyawan',
            self::SISTEM_PENILAIAN_KARYAWAN => 'Sistem Penilaian Karyawan',
            self::SISTEM_APPROVAL => 'Approver',
            self::TUGAS_SAYA => 'Tugas Saya',
            self::PELATIHAN_SAYA => 'Pelatihan Saya',
            self::AJUKAN_PELATIHAN => 'Ajukan Pelatihan (Eksternal)',
            self::PENGAJUAN_ABSENSI_SAYA => 'Pengajuan Absensi Saya',
            self::PEMBINAAN_SAYA => 'Pembinaan Saya',
            self::COACHING_KARYAWAN_LAINNYA => 'Coaching Karyawan Lainnya (Semua Karyawan, Bukan Cuma Bawahan)',
            self::APPROVE_PENGAJUAN_ABSENSI => 'Approve Pengajuan Absensi',
            self::VIEW_REPORT_DATA_ABSENSI => 'Lihat Laporan Data Absensi',
            self::IMPORT_DATA_ABSENSI => 'Import Data Absensi',
            self::APPROVE_PENGAJUAN_PELATIHAN => 'Approve Pengajuan Pelatihan',
            self::APPROVE_MANAJEMEN_LOWONGAN_KERJA => 'Approve Manajemen Lowongan Kerja',
            self::LIHAT_PERUSAHAAN_LAIN => 'Lihat Perusahaan Lain',
            self::IMPORT_KARYAWAN => 'Import Akun Karyawan',
            self::EDIT_MAPPING_KOMPONEN_GAJI => 'Edit Mapping Komponen Gaji',
            self::EDIT_PENERIMA_KOMPONEN_GAJI => 'Edit Penerima Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI => 'Edit Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI_GLOBAL => 'Edit Pengaturan Global Komponen Gaji',
            self::LIHAT_KOMPONEN_GAJI => 'Lihat Komponen Gaji',
            self::LIHAT_PENERIMA_KOMPONEN_GAJI => 'Lihat Penerima Komponen Gaji',
            self::LIHAT_MAPPING_KOMPONEN_GAJI => 'Lihat Mapping Komponen Gaji',
            self::LIHAT_OMSET => 'Lihat Omset',
            self::KELOLA_OMSET => 'Kelola Omset',
            self::LIHAT_INPUT_JASA_UNIT => 'Lihat Input Jasa Unit',
            self::KELOLA_INPUT_JASA_UNIT => 'Kelola Input Jasa Unit',
            self::LIHAT_JOB_EVALUATION => 'Lihat Job Evaluation',
            self::KELOLA_JOB_EVALUATION => 'Kelola Job Evaluation',
            self::EDIT_PAYROLL_ADJUSTMENT => 'Edit Penyesuaian Manual Payroll (Adjustment)',
            self::EDIT_DETAIL_KURSUS_LMS => 'Edit Detail Kursus LMS',
            self::LIHAT_HONORARIUM_LMS => 'Lihat Honorarium LMS',
            self::EDIT_HONORARIUM_LMS => 'Edit Honorarium LMS',
            self::LIHAT_LAPORAN_PELATIHAN => 'Lihat Laporan Pelatihan',
            self::EDIT_PENGATURAN_ABSENSI => 'Edit Pengaturan Absensi',
            self::LIHAT_PENGATURAN_ABSENSI => 'Lihat Pengaturan Absensi',
            self::SYNC_DATA_ABSENSI => 'Sync Data Absensi (dari Access Logs)',
            self::EDIT_PENGATURAN_TUGAS => 'Edit Pengaturan Tugas',
            self::EDIT_PENGATURAN_LMS => 'Edit Pengaturan LMS',
            self::LIHAT_PENGATURAN_LMS => 'Lihat Pengaturan LMS',
            self::EDIT_PENGATURAN_KEPEGAWAIAN => 'Edit Pengaturan Kepegawaian',
            self::LIHAT_PENGATURAN_KEPEGAWAIAN => 'Lihat Pengaturan Kepegawaian',
            self::KELOLA_JADWAL_SHIFT => 'Kelola Jadwal Shift (Template & Roster)',
            self::GENERATE_PAYROLL => 'Generate/Regenerate Payroll',
            self::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN => 'Lihat Lampiran Identitas Karyawan (KTP/KK)',
            self::LIHAT_GAJI_PEMATERI => 'Lihat Gaji Pemateri',
            self::KELOLA_GAJI_PEMATERI => 'Kelola Gaji Pemateri',
            self::KELOLA_HAK_AKSES => 'Kelola Hak Akses (Portal)',
            self::AKSES_PANEL_ADMIN => 'Akses Panel Admin (Filament)',
            self::JADWAL_SHIFT_SAYA => 'Jadwal Shift Saya',
        };
    }
}
