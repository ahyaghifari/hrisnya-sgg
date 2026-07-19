<?php

namespace App\Enum\Payroll;

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
    case KELEBIHAN_JAM_KERJA = 'kelebihan_jam_kerja';
    case INSENTIF = 'insentif';
    case LEMBUR = 'lembur';
    case JASA_PELAYANAN = 'jasa_pelayanan';
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
            self::KELEBIHAN_JAM_KERJA,
            self::INSENTIF,
            self::LEMBUR,
            self::JASA_PELAYANAN,
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
            self::KELEBIHAN_JAM_KERJA => 'Tunjangan Kelebihan Jam Kerja',
            self::INSENTIF => 'Insentif',
            self::LEMBUR => 'Lembur',
            self::JASA_PELAYANAN => 'Jasa Pelayanan',
            self::FEE_CLAIM => 'Fee Klaim (Jasa Dokter, dst)',
            self::BPJS_KESEHATAN => 'BPJS Kesehatan',
            self::BPJS_KETENAGAKERJAAN => 'BPJS Ketenagakerjaan',
            self::KETERLAMBATAN => 'Keterlambatan',
            self::IZIN_TERLAMBAT_PULANG_CEPAT => 'Izin Terlambat Masuk / Pulang Cepat',
            self::IZIN_SEHARIAN => 'Izin Seharian',
            self::CUTI => 'Cuti',
            self::PAJAK_JASA_DOKTER => 'Pajak Jasa Dokter',
            self::KOPERASI => 'Koperasi',
            self::BON => 'Bon',
        };
    }

    /**
     * FEE_CLAIM & PAJAK_JASA_DOKTER murni data-driven (jalan kalau ada baris fee_items yang
     * belum lunas, berhenti sendiri kalau tidak ada) — TIDAK butuh baris `company_payroll_
     * components` untuk di-toggle admin. Dipakai buat exclude keduanya dari daftar toggle UI
     * (lihat planning Fase 1 §3.1).
     */
    public static function alwaysOn(): array
    {
        return [self::FEE_CLAIM, self::PAJAK_JASA_DOKTER];
    }

    /**
     * Komponen yang case-nya sudah didaftarkan tapi Calculator-nya BELUM dibangun (nunggu
     * jawaban aturan bisnis — lihat planning Fase 1 §2.2). UI toggle harus tampilkan ini
     * sebagai disabled + keterangan "Belum tersedia", bukan disembunyikan.
     *
     * IZIN_SEHARIAN sudah pindah dari sini (Calculator-nya sudah ada, lihat planning Fase 2
     * §4) — IZIN_MENINGGALKAN_JAM_KERJA sudah dihapus total dari enum ini (final: tidak ada
     * dampak payroll sama sekali, lihat analisis-payroll-4 §7B.4), bukan lagi "belum tersedia".
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
            self::KELEBIHAN_JAM_KERJA => ['min_total_working_hours_per_month'],
            self::LEMBUR => ['lembur_metode', 'lembur_minimum_jam'],
            self::BPJS_KESEHATAN, self::BPJS_KETENAGAKERJAAN => ['bpjs_declared_value'],
            self::KETERLAMBATAN => ['keterlambatan_threshold_menit', 'keterlambatan_denda', 'keterlambatan_grace_count'],
            self::IZIN_TERLAMBAT_PULANG_CEPAT => ['izin_potongan_flat'],
            self::PAJAK_JASA_DOKTER => ['pajak_jasa_dokter_rate'],
            self::INSENTIF => ['insentif_metode'],
            default => [],
        };
    }
}
