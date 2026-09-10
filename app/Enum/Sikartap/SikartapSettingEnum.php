<?php

namespace App\Enum\Sikartap;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `sikartap` - pola sama
 * App\Enum\Kepegawaian\KepegawaianSettingEnum. Company-scoped, dibaca lewat
 * Setting::resolve($case->value, $companyId, default: $case->default()).
 *
 * Melengkapi kebutuhan company yang beda-beda alur (instruksi user 2026-08-25): tidak semua
 * company butuh tahap Ide Inovasi, dan MCU sendiri juga tidak wajib di semua company - dua
 * toggle ini dibaca Sikartap::isEligibleForDirectorDecision() supaya gate "boleh lanjut ke
 * Direktur" company-aware, bukan 1 urutan tetap buat semua company.
 */
enum SikartapSettingEnum: string
{
    case WAJIB_IDE_INOVASI = 'sikartap_wajib_ide_inovasi';
    case WAJIB_MCU = 'sikartap_wajib_mcu';
    case IDE_INOVASI_PENGESAHAN_DIREKTUR = 'sikartap_ide_inovasi_pengesahan_direktur';

    public function label(): string
    {
        return match ($this) {
            self::WAJIB_IDE_INOVASI => 'Wajib Ide Inovasi',
            self::WAJIB_MCU => 'Wajib MCU (Medical Check-Up)',
            self::IDE_INOVASI_PENGESAHAN_DIREKTUR => 'Ide Inovasi — Pengesahan Direktur',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::WAJIB_IDE_INOVASI => 'Kalau aktif, karyawan wajib submit ide inovasi (dinilai berjenjang Kabag/Kabid unit asal) setelah wawancara disarankan, sebelum bisa lanjut ke tahap berikutnya. Default nonaktif - sebagian besar company tidak mensyaratkan ini.',
            self::WAJIB_MCU => 'Kalau aktif, karyawan wajib menjalani MCU (Medical Check-Up) dan dinyatakan Fit sebelum bisa diajukan ke Direktur. Nonaktifkan kalau company ini tidak mensyaratkan MCU - tahap MCU langsung dilewati.',
            self::IDE_INOVASI_PENGESAHAN_DIREKTUR => 'Kalau aktif, setelah atasan meluluskan ide inovasi, Direktur harus MENGESAHKAN (approve, bukan menilai ulang) sebelum pengajuan bisa lanjut ke Keputusan Direktur. Direktur bisa menolak → pengajuan terblokir sampai disahkan ulang. Hanya berlaku kalau "Wajib Ide Inovasi" aktif.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::BOOLEAN;
    }

    public function default(): string
    {
        return match ($this) {
            // Ide Inovasi opsional per company (baru RSU Syifa Medika Banjarbaru yang
            // butuh) - default off supaya company existing tidak tiba-tiba kena gate baru.
            self::WAJIB_IDE_INOVASI => '0',
            // MCU sudah jadi bagian alur sejak awal (Fase 3) - default on supaya behavior
            // company existing tidak berubah begitu toggle ini diperkenalkan.
            self::WAJIB_MCU => '1',
            // Pengesahan Direktur ide inovasi - default off, tahap direktur/komisaris memang
            // historisnya opsional & tidak blocking.
            self::IDE_INOVASI_PENGESAHAN_DIREKTUR => '0',
        };
    }
}
