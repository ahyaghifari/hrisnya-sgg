<?php

namespace App\Enum\Attendance;

use App\Enum\Attendance\Concerns\HasCompanyLabelOverride;

/**
 * Jenis Cuti - tetap & sama untuk semua perusahaan (tabel master m_leave_types dihapus
 * 2026-09-20). Nilai enum = key yang dikirim form/API (`type_id`) dan disimpan di
 * attendance_req_leave_details.category. Pola sama PermissionTypeCategoryEnum.
 *
 * - TAHUNAN     : cuti tahunan biasa, kena aturan batas akumulasi/bulan (cuti_tahunan_max_per_bulan)
 * - HAID        : hak bulanan (UU 13/2003 Ps 81), maks 2 hari/bulan, dibayar penuh, perempuan saja
 * - MELAHIRKAN  : UU 13/2003 Ps 82, ~3 bulan (1,5 sebelum + 1,5 sesudah), per kehamilan, surat dokter
 * - KEGUGURAN   : UU 13/2003 Ps 82, ~1,5 bulan / sesuai surat dokter, per kejadian, surat dokter
 *
 * Kuota (`quotaDays()`) tetap sesuai UU/kebijakan, sama untuk semua perusahaan; enum ini juga
 * mendefinisikan ATURAN yang menempel ke tiap jenis.
 *
 * HAID disembunyikan dari pengajuan KHUSUS 2 company (RSU Syifa Medika Banjarbaru & Barabai,
 * lihat HasCompanyLabelOverride) - instruksi user 2026-09-30. Company lain tetap bisa ajukan
 * Cuti Haid normal seperti biasa.
 */
enum LeaveTypeCategoryEnum: string
{
    use HasCompanyLabelOverride;

    case TAHUNAN = 'tahunan';
    case HAID = 'haid';
    case MELAHIRKAN = 'melahirkan';
    case KEGUGURAN = 'keguguran';

    public function label(): string
    {
        return match ($this) {
            self::TAHUNAN => 'Cuti Tahunan',
            self::HAID => 'Cuti Haid',
            self::MELAHIRKAN => 'Cuti Melahirkan',
            self::KEGUGURAN => 'Cuti Keguguran',
        };
    }

    /** Cuti khusus (hak spesifik UU, bukan potong kuota cuti tahunan). */
    public function isCutiKhusus(): bool
    {
        return in_array($this, [self::HAID, self::MELAHIRKAN, self::KEGUGURAN], true);
    }

    /** Cuti yang cuma bisa diajukan karyawan perempuan (user_profiles.gender). */
    public function isFemaleOnly(): bool
    {
        return in_array($this, [self::HAID, self::MELAHIRKAN, self::KEGUGURAN], true);
    }

    /** Wajib lampiran surat dokter/bidan (semua cuti khusus). */
    public function requiresAttachment(): bool
    {
        return $this->isCutiKhusus();
    }

    /**
     * Cuti yang total_days & kuotanya dihitung HARI KALENDER, bukan hari kerja jadwal. UU
     * 13/2003 Ps.82 mendefinisikan hak MELAHIRKAN (90 hari)/KEGUGURAN (45 hari) sebagai
     * rentang kalender kontinu (1,5 bulan sebelum + 1,5 bulan sesudah) - dihitung hari kerja
     * saja bikin karyawan berjadwal 5 hari/minggu bisa ambil rentang kalender jauh lebih
     * panjang dari haknya tapi tetap lolos validasi kuota per-pengajuan.
     */
    public function countsCalendarDays(): bool
    {
        return in_array($this, [self::MELAHIRKAN, self::KEGUGURAN], true);
    }

    /**
     * Bagaimana pemakaian ("used") dihitung untuk validasi & tampilan sisa:
     * - 'yearly'      : Σ hari approved dalam tahun kalender berjalan (TAHUNAN)
     * - 'monthly'     : Σ hari approved dalam bulan kalender berjalan (HAID — hak 2 hari/bulan)
     * - 'per_request' : tidak ada saldo berjalan; cukup validasi total_days 1 pengajuan ≤ quotaDays()
     *   (MELAHIRKAN/KEGUGURAN — hak per kejadian, bukan akumulasi)
     */
    public function usageWindow(): string
    {
        return match ($this) {
            self::TAHUNAN => 'yearly',
            self::HAID => 'monthly',
            self::MELAHIRKAN, self::KEGUGURAN => 'per_request',
        };
    }

    /** Kuota hari sesuai UU/kebijakan (tahunan: per tahun, haid: per bulan, melahirkan/keguguran: per pengajuan). */
    public function quotaDays(): int
    {
        return match ($this) {
            self::TAHUNAN => 12,
            self::HAID => 2,
            self::MELAHIRKAN => 90,
            self::KEGUGURAN => 45,
        };
    }
}
