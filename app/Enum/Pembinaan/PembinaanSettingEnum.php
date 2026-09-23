<?php

namespace App\Enum\Pembinaan;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `pembinaan` (planning/2026-09-23-sp-dan-merit-point.md
 * §0, §3.1) - pola sama App\Enum\Kepegawaian\KepegawaianSettingEnum. Company-scoped, dibaca lewat
 * Setting::resolve($case->value, $companyId, default: $case->default()). Satu modul "Pembinaan"
 * menaungi setting SP + Merit Point sekaligus karena keduanya digabung ke gerbang
 * SISTEM_PEMBINAAN_KARYAWAN yang sama.
 */
enum PembinaanSettingEnum: string
{
    case SP1_DURASI_HARI = 'pembinaan_sp1_durasi_hari';
    case SP2_DURASI_HARI = 'pembinaan_sp2_durasi_hari';
    case MERIT_POINT_ENABLED = 'pembinaan_merit_point_enabled';

    public function label(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => 'Durasi SP 1 (Hari)',
            self::SP2_DURASI_HARI => 'Durasi SP 2 (Hari)',
            self::MERIT_POINT_ENABLED => 'Aktifkan Merit Point',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => 'Berapa hari SP 1 berlaku sejak diterbitkan sebelum otomatis dianggap expired. SP 3 selalu berlaku tanpa batas waktu.',
            self::SP2_DURASI_HARI => 'Berapa hari SP 2 berlaku sejak diterbitkan sebelum otomatis dianggap expired.',
            self::MERIT_POINT_ENABLED => 'Kalau dimatikan, seluruh tampilan Merit Point (widget tab Karir, menu rule, tombol beri poin) disembunyikan total untuk company ini.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return match ($this) {
            self::SP1_DURASI_HARI, self::SP2_DURASI_HARI => SettingTypeEnum::INTEGER,
            self::MERIT_POINT_ENABLED => SettingTypeEnum::BOOLEAN,
        };
    }

    public function default(): string
    {
        return match ($this) {
            self::SP1_DURASI_HARI => '90',
            self::SP2_DURASI_HARI => '180',
            self::MERIT_POINT_ENABLED => 'false',
        };
    }
}
