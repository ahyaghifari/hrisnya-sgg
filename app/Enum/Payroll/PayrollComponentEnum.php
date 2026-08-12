<?php

namespace App\Enum\Payroll;

use App\Models\Setting;

/**
 * Vocabulary TERTUTUP komponen payroll — dunia komponen gaji perusahaan ini dikenal/finite
 * (~20 komponen), bukan sesuatu yang admin bikin bebas kapan saja. Menggantikan
 * `salary_components` (katalog generik) + `salary_component_assignments` (assignment
 * polymorphic) + `salary_component_employee_types` (pivot eligibility) — 3 tabel itu diganti
 * `company_payroll_components` (cuma saklar ON/OFF per company + tipe karyawan eligible).
 * Angka/logika aktual tiap komponen ada di Calculator (app/Services/Payroll/Calculators/)
 * + tabel config terpisah (pangkat_salaries, position_allowances, dst, settings).
 *
 * Lihat knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md dan
 * planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md.
 */
enum PayrollComponentEnum: string
{
    // PENDAPATAN
    case GAJI_POKOK = 'gaji_pokok';
    case TUNJANGAN_JABATAN = 'tunjangan_jabatan';
    case TUNJANGAN_TRANSPORTASI = 'tunjangan_transportasi';
    case TUNJANGAN_MASA_KERJA = 'tunjangan_masa_kerja';
    case INSENTIF = 'insentif';
    case LEMBUR = 'lembur';
    case JASA_PELAYANAN = 'jasa_pelayanan';
    case JASA_UNIT = 'jasa_unit';
    case FEE_CLAIM = 'fee_claim';

    // POTONGAN
    case BPJS_KESEHATAN = 'bpjs_kesehatan';
    case BPJS_KETENAGAKERJAAN = 'bpjs_ketenagakerjaan';
    case KETERLAMBATAN = 'keterlambatan';
    case IZIN_TERLAMBAT_PULANG_CEPAT = 'izin_terlambat_pulang_cepat';
    case IZIN_SEHARIAN = 'izin_seharian';
    case CUTI = 'cuti';
    case PAJAK_JASA_DOKTER = 'pajak_jasa_dokter';
    case KOPERASI = 'koperasi';
    case BON = 'bon';

    /**
     * earning/deduction diturunkan dari komponennya sendiri (pola sama
     * SalaryComponentCategoryEnum::type() lama) — tidak ada field type terpisah yang bisa
     * disetel salah.
     */
    public function type(): PayrollComponentTypeEnum
    {
        return match ($this) {
            self::GAJI_POKOK,
            self::TUNJANGAN_JABATAN,
            self::TUNJANGAN_TRANSPORTASI,
            self::TUNJANGAN_MASA_KERJA,
            self::INSENTIF,
            self::LEMBUR,
            self::JASA_PELAYANAN,
            self::JASA_UNIT,
            self::FEE_CLAIM => PayrollComponentTypeEnum::EARNING,

            self::BPJS_KESEHATAN,
            self::BPJS_KETENAGAKERJAAN,
            self::KETERLAMBATAN,
            self::IZIN_TERLAMBAT_PULANG_CEPAT,
            self::IZIN_SEHARIAN,
            self::CUTI,
            self::PAJAK_JASA_DOKTER,
            self::KOPERASI,
            self::BON => PayrollComponentTypeEnum::DEDUCTION,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GAJI_POKOK => 'Gaji Pokok',
            self::TUNJANGAN_JABATAN => 'Tunjangan Jabatan',
            self::TUNJANGAN_TRANSPORTASI => 'Tunjangan Transportasi',
            self::TUNJANGAN_MASA_KERJA => 'Tunjangan Masa Kerja',
            self::INSENTIF => 'Insentif',
            self::LEMBUR => 'Lembur',
            self::JASA_PELAYANAN => 'Jasa Pelayanan',
            self::JASA_UNIT => 'Jasa Unit',
            self::FEE_CLAIM => 'Fee Klaim (Jasa Dokter, dst)',
            self::BPJS_KESEHATAN => 'BPJS Kesehatan',
            self::BPJS_KETENAGAKERJAAN => 'BPJS Ketenagakerjaan',
            self::KETERLAMBATAN => 'Keterlambatan',
            self::IZIN_TERLAMBAT_PULANG_CEPAT => 'Izin Meninggalkan Jam Kerja',
            self::IZIN_SEHARIAN => 'Izin Tidak Masuk',
            self::CUTI => 'Cuti',
            self::PAJAK_JASA_DOKTER => 'Pajak Jasa Dokter',
            self::KOPERASI => 'Koperasi',
            self::BON => 'Bon',
        };
    }

    /**
     * FEE_CLAIM murni data-driven (jalan kalau ada baris fee_items yang belum lunas, berhenti
     * sendiri kalau tidak ada) — TIDAK butuh baris `company_payroll_components` untuk
     * di-toggle admin. Dipakai buat exclude dari daftar toggle UI (lihat planning Fase 1
     * §3.1). PAJAK_JASA_DOKTER sekarang bisa di-toggle per company seperti komponen lain —
     * FeeClaimCalculator cek `company_payroll_components` sebelum motong pajaknya.
     */
    public static function alwaysOn(): array
    {
        return [self::FEE_CLAIM];
    }

    /**
     * Komponen yang case-nya sudah didaftarkan tapi Calculator-nya BELUM dibangun (nunggu
     * jawaban aturan bisnis — lihat planning Fase 1 §2.2). UI toggle harus tampilkan ini
     * sebagai disabled + keterangan "Belum tersedia", bukan disembunyikan.
     *
     * IZIN_SEHARIAN sudah pindah dari sini (Calculator-nya sudah ada, lihat planning Fase 2
     * §4). Kategori absensi IZIN_MENINGGALKAN_JAM_KERJA (PermissionTypeCategoryEnum, sub-jenis
     * "tengah hari") SEMPAT final tidak ada dampak payroll (analisis-payroll-4 §7B.4) — direvisi
     * lagi 2026-07-31, sekarang digabung ke komponen IZIN_TERLAMBAT_PULANG_CEPAT lewat
     * IzinMeninggalkanJamKerjaCalculator (lihat knowledge/markdown/rencana-izin-empat-kategori.md).
     */
    public static function notYetAvailable(): array
    {
        return [];
    }

    /**
     * Key `settings` yang WAJIB diisi untuk komponen ini — muncul otomatis di bawah komponen
     * saat di-expand di halaman Komponen Gaji. SELALU company-scoped (beda dari
     * GlobalPayrollSettingEnum yang selalu global) — nilainya spesifik per company yang
     * lagi dipilih, bukan dibagi semua company. Metadata (label/tipe/keterangan) tiap key
     * dibaca dari baris `settings` itu sendiri (PayrollSettingsSeeder), bukan didup di sini.
     *
     * @return string[]
     */
    public function requiredSettings(): array
    {
        return match ($this) {
            self::GAJI_POKOK => ['gaji_pokok_kenaikan_per_kelas'],
            self::BPJS_KESEHATAN, self::BPJS_KETENAGAKERJAAN => ['bpjs_declared_value'],
            self::KETERLAMBATAN => ['keterlambatan_threshold_menit', 'keterlambatan_denda', 'keterlambatan_grace_count'],
            self::IZIN_TERLAMBAT_PULANG_CEPAT => ['izin_potongan_flat'],
            self::PAJAK_JASA_DOKTER => ['pajak_jasa_dokter_rate'],
            self::INSENTIF => ['insentif_metode'],
            self::LEMBUR => ['lembur_minimum_jam'],
            self::IZIN_SEHARIAN => ['izin_seharian_metode', 'izin_seharian_persen_gaji_pokok'],
            self::BON => ['bon_metode'],
            default => [],
        };
    }

    /**
     * Nama baris payroll_items yang membekukan rate/threshold pada SAAT digenerate (BPJS/
     * Keterlambatan/Lembur) — dipakai baik oleh Calculator (baris asli) maupun fallback Rp0 di
     * PayrollService::runComponent() saat Calculator tidak membuat baris sama sekali. Sengaja
     * TIDAK live-reconstruct di componentRows() lagi (2026-08-02) - kalau master setting berubah
     * bulan depan, slip bulan lalu jangan ikut berubah. BPJS pakai company_id null (global),
     * cocok cara AbstractBpjsCalculator sendiri resolve rate-nya.
     */
    public function defaultRowName(?string $companyId): string
    {
        return match ($this) {
            self::BPJS_KESEHATAN => $this->label() . ' ' . Setting::resolve(GlobalPayrollSettingEnum::BPJS_KESEHATAN_RATE->value, null, default: 0) . '%',
            self::BPJS_KETENAGAKERJAAN => $this->label() . ' ' . Setting::resolve(GlobalPayrollSettingEnum::BPJS_KETENAGAKERJAAN_RATE->value, null, default: 0) . '%',
            self::KETERLAMBATAN => $this->label() . ' > ' . (int) Setting::resolve('keterlambatan_threshold_menit', $companyId, default: 0) . ' menit',
            self::LEMBUR => $this->label() . ' (Dihitung > ' . (int) Setting::resolve('lembur_minimum_jam', $companyId, default: 2) . ' jam)',
            default => $this->label(),
        };
    }

    /**
     * Note default buat baris fallback Rp0 (0 kejadian) di PayrollService::runComponent() -
     * dulu disintesis live di componentRows(), sekarang dibekukan di baris itu sendiri saat
     * digenerate (2026-08-02), sama filosofinya kayak defaultRowName(). Kalkulator yang beneran
     * punya kejadian nulis notenya sendiri dengan hitungan real - method ini cuma dipakai
     * fallback.
     *
     * SENGAJA selalu null (2026-08-12, sebelumnya Cuti/Izin Tidak Masuk "0x/Hari" dan
     * Keterlambatan/Izin Meninggalkan Jam Kerja "0x/Bulan") - kalau tidak ada kejadian sama
     * sekali (atau kejadiannya masih dalam toleransi/grace buat Keterlambatan), kolom keterangan
     * di slip dibiarkan kosong, bukan menampilkan "0x/Bulan"/"0x/Hari" yang kesannya seperti ada
     * baris/kejadian padahal tidak ada.
     */
    public function defaultRowNote(): ?string
    {
        return null;
    }
}
