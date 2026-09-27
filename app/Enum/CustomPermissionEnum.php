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

    /** Gerbang dropdown nav "Sikartap" (row 2) — sama pola SISTEM_ABSENSI/SISTEM_KEUANGAN dkk:
     * gerbang broad "boleh akses modul ini sama sekali" (HRD/Direktur/staff klinis MCU semua
     * butuh ini dulu sebelum link di dalam dropdown dicek granular masing-masing:
     * ViewAny:Sikartap buat Pengajuan/Jadwal, INPUT_MCU_* buat Antrian MCU). Beda dari
     * canAccessSikartapSaya (isKontrakKaryawan()) yang gerbang link self-service row 1
     * "Sikartap" buat karyawan mengajukan, tidak butuh permission ini sama sekali. */
    case SISTEM_SIKARTAP = 'sistem_sikartap';

    /** Gerbang nav group "Rekrutmen" (modul sendiri sejak 2026-08-28, dulu numpang di
     * Kepegawaian) — sama pola SISTEM_SIKARTAP: gerbang broad "boleh akses modul ini". Link di
     * dalamnya tetap dicek granular (ViewAny:JobOpenings buat Kelola Lowongan, INPUT_MCU_*
     * buat Antrian MCU rekrutmen). Container admin 'Rekrutmen' dilebur ke gerbang ini lewat
     * RoleForm::containerMerge(). */
    case SISTEM_REKRUTMEN = 'sistem_rekrutmen';

    /** Gerbang modul "MCU" berdiri sendiri — staf klinis (perawat/lab/radiologi/dokter) yang
     * cuma bertugas mengisi hasil MCU tidak perlu lagi diberi SISTEM_SIKARTAP / SISTEM_REKRUTMEN
     * (yang membuka seluruh modul itu). Link di dalamnya tetap dicek granular per stasiun lewat
     * INPUT_MCU_* / EDIT_INPUT_MCU. */
    case SISTEM_MCU = 'sistem_mcu';

    case TUGAS_SAYA = 'tugas_saya';

    /** Boleh membuat Board baru di modul Tugas (tombol "Board Baru") - anggota Board lain tidak terpengaruh. */
    case BUAT_BOARD_TUGAS = 'buat_board_tugas';
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
    case VIEW_REPORT_DATA_ABSENSI = 'view_report_data_absensi';
    case APPROVE_PENGAJUAN_PELATIHAN = 'approve_pengajuan_pelatihan';
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

    /** Gerbang halaman portal "Pajak (%)" (`/keuangan/pajak`, potongan 3 kolom Gaji Pokok dari
     * PayrollSlipRekapExport buat basis PPh21) - dipisah dari ViewAny:Payroll (2026-08-22)
     * supaya bisa dikasih ke role finance yang lebih terbatas (mis. tim pajak) tanpa otomatis
     * bisa buka halaman Payroll penuh, sama pola LIHAT_OMSET/LIHAT_KOMPONEN_GAJI. */
    case LIHAT_PAJAK = 'lihat_pajak';

    /** Gerbang halaman portal "Data Rekening" (`/keuangan/data-rekening`, kelola bank_name/
     * bank_account_number/bank_account_name di UserProfiles) - dipisah dari Update:User (2026-08-26)
     * supaya finance/payroll bisa diberi akses ubah rekening TANPA otomatis bisa edit seluruh
     * data karyawan lain (jabatan/kontrak/dst), sama pola LIHAT_PAJAK/EDIT_KOMPONEN_GAJI. */
    case LIHAT_DATA_REKENING = 'lihat_data_rekening';
    case EDIT_DATA_REKENING = 'edit_data_rekening';

    /** Gerbang halaman portal "Override Komponen Gaji" (`/keuangan/override-komponen-gaji`,
     * kelola payroll_component_overrides) - override manual per karyawan+komponen apa pun,
     * Calculator-nya di-skip total kalau ada override aktif (2026-08-26, instruksi user). */
    case LIHAT_OVERRIDE_KOMPONEN_GAJI = 'lihat_override_komponen_gaji';
    case EDIT_OVERRIDE_KOMPONEN_GAJI = 'edit_override_komponen_gaji';

    /** Gerbang halaman portal "Kenaikan Gaji Pokok" (`/keuangan/kenaikan-gaji-pokok`, kelola
     * gaji_pokok_kenaikan_overrides) - master persen kenaikan Gaji Pokok per user, pengganti
     * App\Enum\Payroll\GajiPokokKenaikanOverrideEnum yang hardcode 1 user (2026-09-04, ternyata
     * bukan cuma 1 orang). Beda dari LIHAT/EDIT_OVERRIDE_KOMPONEN_GAJI (Gaji Khusus) - itu
     * menimpa nominal komponen apa pun, ini cuma persen kenaikan Gaji Pokok, dan Gaji Khusus
     * tetap prioritas lebih tinggi (skip Calculator total kalau ada baris aktif). */
    case LIHAT_KENAIKAN_GAJI_POKOK = 'lihat_kenaikan_gaji_pokok';
    case EDIT_KENAIKAN_GAJI_POKOK = 'edit_kenaikan_gaji_pokok';

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

    /** Halaman portal "Alur Persetujuan" (`/kepegawaian/alur-persetujuan`) - versi portal dari
     * admin Filament `ApprovalFlowResource`, dikelompokkan Kepegawaian (instruksi user
     * 2026-08-16) meski konsepnya lintas-modul (approval flow dipakai banyak modul) - alasan
     * sama LIHAT_JOB_EVALUATION, halaman config tanpa Filament Resource sendiri. LIHAT = boleh
     * lihat daftar, KELOLA = boleh tambah/ubah (form tambah/edit digerbangi KELOLA saja, tidak
     * ada mode lihat-doang di form). */
    case LIHAT_ALUR_PERSETUJUAN = 'lihat_alur_persetujuan';
    case KELOLA_ALUR_PERSETUJUAN = 'kelola_alur_persetujuan';

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
    case EDIT_PENGATURAN_LMS = 'edit_pengaturan_lms';
    case EDIT_PENGATURAN_KEPEGAWAIAN = 'edit_pengaturan_kepegawaian';

    /** Buka halaman Pengaturan Kepegawaian (read-only) - beda dari EDIT_PENGATURAN_KEPEGAWAIAN
     * yang menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali (bukan cuma
     * disembunyikan tombol simpan/input-nya), sama seperti pola LIHAT_PENGATURAN_ABSENSI. */
    case LIHAT_PENGATURAN_KEPEGAWAIAN = 'lihat_pengaturan_kepegawaian';

    /** Widget "Buka/Tutup Pengajuan Karyawan Tetap" di Dashboard Sikartap (toggle
     * KepegawaianSettingEnum::PENGANGKATAN_DIBUKA, lihat DashboardSikartapPage) - dikelompokkan
     * Sikartap (instruksi user 2026-09-27), bukan Kepegawaian, walau setting-nya sendiri masih
     * baris `settings` module kepegawaian. Dipisah dari EDIT_PENGATURAN_KEPEGAWAIAN supaya HC/SDM
     * yang cuma bertugas membuka-menutup periode tidak otomatis bisa mengubah pengaturan
     * kepegawaian lain. Butuh SISTEM_SIKARTAP + LIHAT_DASHBOARD_SIKARTAP_MODUL buat membuka
     * halamannya (gate DashboardSikartapPage::mount(), bukan permission Kepegawaian). Pemegang
     * EDIT_PENGATURAN_KEPEGAWAIAN otomatis juga boleh toggle (edit = semua pengaturan). */
    case BUKA_TUTUP_PENGAJUAN_KARTAP = 'buka_tutup_pengajuan_kartap';

    /** Halaman "Pengaturan Sikartap" (2026-08-25) - toggle per company wajib Ide Inovasi &
     * wajib MCU (App\Enum\Sikartap\SikartapSettingEnum), pola sama persis
     * LIHAT/EDIT_PENGATURAN_KEPEGAWAIAN: LIHAT = buka halaman (read-only tanpa EDIT), EDIT =
     * boleh simpan perubahan. */
    case LIHAT_PENGATURAN_SIKARTAP = 'lihat_pengaturan_sikartap';
    case EDIT_PENGATURAN_SIKARTAP = 'edit_pengaturan_sikartap';

    /** Fase 5 Sikartap (Ide Inovasi) - tahap 2 & 3, opsional & tidak blocking (beda dari tahap 1
     * atasan yang wajib & dicari dinamis lewat Sikartap::ideInovasiAtasanCandidates(), bukan
     * permission). Direktur/Komisaris di-assign manual lewat halaman Hak Akses. */
    case NILAI_IDE_INOVASI_DIREKTUR = 'nilai_ide_inovasi_direktur';
    case NILAI_IDE_INOVASI_KOMISARIS = 'nilai_ide_inovasi_komisaris';

    /** Dashboard Sikartap (2026-08-25, instruksi user) - 1 permission per widget, bukan 1 gerbang
     * besar, supaya role staff MCU/HRD/Direktur/atasan Ide Inovasi masing-masing cuma lihat
     * widget yang relevan. Halaman dashboard-nya sendiri bisa diakses siapa saja yang punya
     * SALAH SATU dari 3 ini (dicek manual di mount(), bukan lewat portal-pages.php satu
     * permission karena butuh OR). Subject-nya di-mapping ke container "Sikartap" > sub-grup
     * "Dashboard" lewat RoleForm::customPermissionSubjectOverrides(), pola sama SikartapMcu. */
    case LIHAT_DASHBOARD_SIKARTAP_STATISTIK = 'lihat_dashboard_sikartap_statistik';
    case LIHAT_DASHBOARD_SIKARTAP_ANTRIAN_TAHAP = 'lihat_dashboard_sikartap_antrian_tahap';

    /** Buka halaman Pengaturan LMS (read-only) - beda dari EDIT_PENGATURAN_LMS yang
     * menggerbangi simpan. Tanpa permission ini, halamannya 403 sama sekali, sama pola
     * LIHAT_PENGATURAN_ABSENSI/LIHAT_PENGATURAN_KEPEGAWAIAN. */
    case LIHAT_PENGATURAN_LMS = 'lihat_pengaturan_lms';

    /** Generate/regenerate payroll (PayrollPeriodsPage) — aksi berat/sensitif (nulis ratusan
     * baris payroll sekaligus), sengaja dipisah dari Update:PayrollPeriod (yang cuma ubah
     * tanggal periode) supaya bisa dikasih ke role finance yang lebih terbatas. */
    case GENERATE_PAYROLL = 'generate_payroll';

    /** Import Payroll Manual dari Excel (ManualPayrollImportPage, /keuangan/import-payroll-manual)
     * — transkripsi angka gaji final dari file, bukan hasil calculator. Dipisah dari
     * GENERATE_PAYROLL supaya bisa dikasih ke petugas yang cuma meng-input hasil Excel tanpa
     * ikut bisa Generate/Regenerate payroll biasa. Aksi Tandai Dibayar/Kirim Slip di halaman
     * itu tetap butuh Update:Payroll terpisah. */
    case IMPORT_PAYROLL_MANUAL = 'import_payroll_manual';

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

    /** Gerbang MASUK ke panel Filament khusus Keycloak (`/keycloak`) — kelola user Keycloak
     * lewat Admin REST API (App\Services\Keycloak\KeycloakAdmin). Pola sama SISTEM_REKRUTMEN:
     * User::canAccessPanel() cek role "Admin"/"Super Admin" dulu, permission ini jalur kedua
     * buat role IT/helpdesk yang cuma perlu kelola akun SSO tanpa akses /admin penuh. */
    case KELOLA_KEYCLOAK = 'kelola_keycloak';

    /** Tombol "Export Excel" di halaman Karyawan (`/kepegawaian/karyawan`) - export data
     * akun+pribadi+pekerjaan sesuai filter aktif. Dipisah dari akses ViewAny:User biasa (yang
     * sudah dicek KaryawanPage::mount()) karena datanya sensitif (NIK/KK/BPJS/rekening bank) -
     * role yang cuma boleh LIHAT daftar karyawan belum tentu boleh menarik semuanya ke Excel. */
    case EXPORT_DATA_KARYAWAN = 'export_data_karyawan';

    /** Gerbang 4 form input MCU Sikartap (`app/Livewire/Sikartap/Mcu/*`) — masing-masing role
     * klinis (perawat/lab/radiologi/dokter) cuma isi bagiannya sendiri, sama pola pemisahan
     * check_role_any() per form di legacy (form_asesmen_perawat.php dkk). Bukan Shield
     * Verb:Model karena bukan CRUD Resource biasa - sama pola LIHAT_OMSET/KELOLA_OMSET (portal
     * page tanpa Filament Resource sendiri). */
    case INPUT_MCU_ASESMEN_PERAWAT = 'input_mcu_asesmen_perawat';
    case INPUT_MCU_LABORATORIUM = 'input_mcu_laboratorium';
    case INPUT_MCU_RADIOLOGI = 'input_mcu_radiologi';
    case INPUT_MCU_PEMERIKSAAN_DOKTER = 'input_mcu_pemeriksaan_dokter';

    /** Edit ulang hasil MCU yang SUDAH pernah diisi (2026-08-26, instruksi user) - beda dari
     * INPUT_MCU_* yang cuma boleh isi data yang masih kosong. Sengaja permission terpisah &
     * sempit (ditujukan buat segelintir orang), supaya kalau ada salah input masih bisa
     * diperbaiki tanpa staff biasa bisa asal ubah data yang sudah final. Berlaku lintas 4
     * sub-form MCU (perawat/lab/radiologi/dokter), bukan dipecah per role seperti INPUT_MCU_*
     * karena "yang boleh koreksi" itu peran pengawasan, bukan peran pengisi data. */
    case EDIT_INPUT_MCU = 'edit_input_mcu';

    /** Gerbang halaman Dashboard masing2 modul - dulu numpang gerbang modul (SISTEM_KEPEGAWAIAN
     * dkk), otomatis kebuka buat siapa saja yang punya akses modul itu (instruksi user
     * 2026-09-18: mau bisa dimatikan terpisah per role). Dicek DI SAMPING (AND) SISTEM_* modul-nya
     * yang sudah ada di mount()/canAccess() masing2 halaman, bukan pengganti - role tetap butuh
     * akses modul dulu, permission ini cuma mempersempit siapa yang boleh lihat dashboard-nya. */
    case LIHAT_DASHBOARD_KEPEGAWAIAN = 'lihat_dashboard_kepegawaian';
    case LIHAT_DASHBOARD_KEUANGAN = 'lihat_dashboard_keuangan';
    case LIHAT_DASHBOARD_PRESENSI = 'lihat_dashboard_presensi';
    case LIHAT_DASHBOARD_SIKARTAP_MODUL = 'lihat_dashboard_sikartap_modul';
    case LIHAT_DASHBOARD_REKRUTMEN = 'lihat_dashboard_rekrutmen';

    public function label(): string
    {
        return match
         ($this) {
            self::SISTEM_KEUANGAN => 'Sistem Keuangan',
            self::SISTEM_ABSENSI => 'Sistem Presensi & Absensi',
            self::SISTEM_KEPEGAWAIAN => 'Sistem Kepegawaian',
            self::SISTEM_LMS => 'Sistem LMS',
            self::SISTEM_PEMBINAAN_KARYAWAN => 'Sistem Pembinaan Karyawan',
            self::SISTEM_PENILAIAN_KARYAWAN => 'Sistem Penilaian Karyawan',
            self::SISTEM_SIKARTAP => 'Sistem Sikartap',
            self::SISTEM_REKRUTMEN => 'Sistem Rekrutmen',
            self::SISTEM_MCU => 'Sistem MCU',
            self::TUGAS_SAYA => 'Tugas Saya',
            self::BUAT_BOARD_TUGAS => 'Buat Board Tugas',
            self::PELATIHAN_SAYA => 'Pelatihan Saya',
            self::AJUKAN_PELATIHAN => 'Ajukan Pelatihan (Eksternal)',
            self::PENGAJUAN_ABSENSI_SAYA => 'Pengajuan Absensi Saya',
            self::PEMBINAAN_SAYA => 'Pembinaan Saya',
            self::COACHING_KARYAWAN_LAINNYA => 'Coaching Karyawan Lainnya (Semua Karyawan, Bukan Cuma Bawahan)',
            self::VIEW_REPORT_DATA_ABSENSI => 'Lihat Laporan Data Absensi',
            self::APPROVE_PENGAJUAN_PELATIHAN => 'Approve Pengajuan Pelatihan',
            self::LIHAT_PERUSAHAAN_LAIN => 'Lihat Perusahaan Lain',
            self::IMPORT_KARYAWAN => 'Import Akun Karyawan',
            self::EDIT_MAPPING_KOMPONEN_GAJI => 'Edit Mapping Komponen Gaji',
            self::EDIT_PENERIMA_KOMPONEN_GAJI => 'Edit Penerima Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI => 'Edit Komponen Gaji',
            self::EDIT_KOMPONEN_GAJI_GLOBAL => 'Edit Pengaturan Global Komponen Gaji',
            self::LIHAT_KOMPONEN_GAJI => 'Lihat Komponen Gaji',
            self::LIHAT_PENERIMA_KOMPONEN_GAJI => 'Lihat Penerima Komponen Gaji',
            self::LIHAT_MAPPING_KOMPONEN_GAJI => 'Lihat Mapping Komponen Gaji',
            self::LIHAT_PAJAK => 'Lihat Pajak (%)',
            self::LIHAT_DATA_REKENING => 'Lihat Data Rekening',
            self::EDIT_DATA_REKENING => 'Edit Data Rekening',
            self::LIHAT_OVERRIDE_KOMPONEN_GAJI => 'Lihat Gaji Khusus',
            self::EDIT_OVERRIDE_KOMPONEN_GAJI => 'Edit Gaji Khusus',
            self::LIHAT_KENAIKAN_GAJI_POKOK => 'Lihat Kenaikan Gaji Pokok',
            self::EDIT_KENAIKAN_GAJI_POKOK => 'Edit Kenaikan Gaji Pokok',
            self::LIHAT_OMSET => 'Lihat Omset',
            self::KELOLA_OMSET => 'Kelola Omset',
            self::LIHAT_INPUT_JASA_UNIT => 'Lihat Input Jasa Unit',
            self::KELOLA_INPUT_JASA_UNIT => 'Kelola Input Jasa Unit',
            self::LIHAT_JOB_EVALUATION => 'Lihat Job Evaluation',
            self::KELOLA_JOB_EVALUATION => 'Kelola Job Evaluation',
            self::LIHAT_ALUR_PERSETUJUAN => 'Lihat Alur Persetujuan',
            self::KELOLA_ALUR_PERSETUJUAN => 'Kelola Alur Persetujuan',
            self::EDIT_PAYROLL_ADJUSTMENT => 'Edit Penyesuaian Manual Payroll (Adjustment)',
            self::EDIT_DETAIL_KURSUS_LMS => 'Edit Detail Kursus LMS',
            self::LIHAT_HONORARIUM_LMS => 'Lihat Honorarium LMS',
            self::EDIT_HONORARIUM_LMS => 'Edit Honorarium LMS',
            self::LIHAT_LAPORAN_PELATIHAN => 'Lihat Laporan Pelatihan',
            self::EDIT_PENGATURAN_ABSENSI => 'Edit Pengaturan Absensi',
            self::LIHAT_PENGATURAN_ABSENSI => 'Lihat Pengaturan Absensi',
            self::SYNC_DATA_ABSENSI => 'Sync Data Absensi (dari Access Logs)',
            self::EDIT_PENGATURAN_LMS => 'Edit Pengaturan LMS',
            self::LIHAT_PENGATURAN_LMS => 'Lihat Pengaturan LMS',
            self::EDIT_PENGATURAN_KEPEGAWAIAN => 'Edit Pengaturan Kepegawaian',
            self::LIHAT_PENGATURAN_KEPEGAWAIAN => 'Lihat Pengaturan Kepegawaian',
            self::BUKA_TUTUP_PENGAJUAN_KARTAP => 'Buka/Tutup Pengajuan Karyawan Tetap',
            self::LIHAT_PENGATURAN_SIKARTAP => 'Lihat Pengaturan Sikartap',
            self::EDIT_PENGATURAN_SIKARTAP => 'Edit Pengaturan Sikartap',
            self::NILAI_IDE_INOVASI_DIREKTUR => 'Nilai Ide Inovasi (Direktur)',
            self::NILAI_IDE_INOVASI_KOMISARIS => 'Nilai Ide Inovasi (Komisaris)',
            self::LIHAT_DASHBOARD_SIKARTAP_STATISTIK => 'Lihat Widget Statistik Pengajuan (Dashboard Sikartap)',
            self::LIHAT_DASHBOARD_SIKARTAP_ANTRIAN_TAHAP => 'Lihat Widget Antrian per Tahap (Dashboard Sikartap)',
            self::GENERATE_PAYROLL => 'Generate/Regenerate Payroll',
            self::IMPORT_PAYROLL_MANUAL => 'Import Payroll Manual (dari Excel)',
            self::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN => 'Lihat Lampiran Identitas Karyawan (KTP/KK)',
            self::LIHAT_GAJI_PEMATERI => 'Lihat Gaji Pemateri',
            self::KELOLA_GAJI_PEMATERI => 'Kelola Gaji Pemateri',
            self::KELOLA_HAK_AKSES => 'Kelola Hak Akses (Portal)',
            self::AKSES_PANEL_ADMIN => 'Akses Panel Admin (Filament)',
            self::KELOLA_KEYCLOAK => 'Kelola Keycloak (Panel /keycloak)',
            self::EXPORT_DATA_KARYAWAN => 'Export Data Karyawan (Excel)',
            self::INPUT_MCU_ASESMEN_PERAWAT => 'Input MCU - Asesmen Perawat',
            self::INPUT_MCU_LABORATORIUM => 'Input MCU - Hasil Laboratorium',
            self::INPUT_MCU_RADIOLOGI => 'Input MCU - Hasil Radiologi',
            self::INPUT_MCU_PEMERIKSAAN_DOKTER => 'Input MCU - Pemeriksaan Dokter',
            self::EDIT_INPUT_MCU => 'Edit Ulang Hasil MCU (Sudah Diisi)',
            self::LIHAT_DASHBOARD_KEPEGAWAIAN => 'Lihat Dashboard Kepegawaian',
            self::LIHAT_DASHBOARD_KEUANGAN => 'Lihat Dashboard Keuangan',
            self::LIHAT_DASHBOARD_PRESENSI => 'Lihat Dashboard Presensi',
            self::LIHAT_DASHBOARD_SIKARTAP_MODUL => 'Lihat Dashboard Sikartap',
            self::LIHAT_DASHBOARD_REKRUTMEN => 'Lihat Dashboard Rekrutmen',
        };
    }
}
