<?php

namespace App\Enum\Pembinaan;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `pembinaan` (planning/2026-09-23-sp-dan-merit-point.md
 * §0, §3.1) - pola sama App\Enum\Kepegawaian\KepegawaianSettingEnum. Company-scoped, dibaca lewat
 * Setting::resolve($case->value, $companyId, default: $case->default()). Satu modul "Pembinaan"
 * menaungi setting SP, gerbangnya SISTEM_PEMBINAAN_KARYAWAN.
 */
enum PembinaanSettingEnum: string
{
    case SP1_DURASI_HARI = 'pembinaan_sp1_durasi_hari';
    case SP2_DURASI_HARI = 'pembinaan_sp2_durasi_hari';
    case SP3_DURASI_HARI = 'pembinaan_sp3_durasi_hari';

    public function label(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => 'Durasi SP 1 (Hari)',
            self::SP2_DURASI_HARI => 'Durasi SP 2 (Hari)',
            self::SP3_DURASI_HARI => 'Durasi SP 3 (Hari)',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => 'Berapa hari SP 1 berlaku sejak diterbitkan sebelum otomatis dianggap expired.',
            self::SP2_DURASI_HARI => 'Berapa hari SP 2 berlaku sejak diterbitkan sebelum otomatis dianggap expired.',
            self::SP3_DURASI_HARI => 'Berapa hari SP 3 berlaku sejak diterbitkan sebelum otomatis dianggap expired.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return match ($this) {
            self::SP1_DURASI_HARI, self::SP2_DURASI_HARI, self::SP3_DURASI_HARI => SettingTypeEnum::INTEGER,
        };
    }

    public function default(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => '90',
            self::SP2_DURASI_HARI => '180',
            self::SP3_DURASI_HARI => '180',
        };
    }
}
