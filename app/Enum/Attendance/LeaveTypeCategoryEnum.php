<?php

namespace App\Enum\Attendance;

/**
 * Kategori tetap `m_leave_types.category` — dipakai buat mengidentifikasi jenis cuti tertentu
 * tanpa cocok nama (fragile). Pola sama PermissionTypeCategoryEnum.
 *
 * - TAHUNAN     : cuti tahunan biasa, kena aturan batas akumulasi/bulan (cuti_tahunan_max_per_bulan)
 * - HAID        : hak bulanan (UU 13/2003 Ps 81), maks 2 hari/bulan, dibayar penuh, perempuan saja
 * - MELAHIRKAN  : UU 13/2003 Ps 82, ~3 bulan (1,5 sebelum + 1,5 sesudah), per kehamilan, surat dokter
 * - KEGUGURAN   : UU 13/2003 Ps 82, ~1,5 bulan / sesuai surat dokter, per kejadian, surat dokter
 *
 * Kuota (`quota_days`) & is_paid tetap dari baris `m_leave_types` (bisa diatur admin per company);
 * enum ini cuma mendefinisikan ATURAN yang menempel ke kategori.
 */
enum LeaveTypeCategoryEnum: string
{
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
     * - 'per_request' : tidak ada saldo berjalan; cukup validasi total_days 1 pengajuan ≤ quota_days
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

    /** Kuota default sesuai UU — cuma acuan buat seeding; nilai final tetap dari m_leave_types.quota_days. */
    public function defaultQuotaDays(): int
    {
        return match ($this) {
            self::TAHUNAN => 12,
            self::HAID => 2,
            self::MELAHIRKAN => 90,
            self::KEGUGURAN => 45,
        };
    }
}
