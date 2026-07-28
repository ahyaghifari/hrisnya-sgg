<?php

namespace App\Enum\LMS;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `lms` — pola sama
 * App\Enum\Attendance\AttendanceSettingEnum. Global (LMS tidak per company, sama
 * seperti Master Honorarium), dibaca lewat Setting::resolve($case->value, null, default: ...).
 */
enum LmsSettingEnum: string
{
    case MENIT_1_JP = 'menit_1_jp';

    public function label(): string
    {
        return match ($this) {
            self::MENIT_1_JP => 'Menit per 1 JP',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::MENIT_1_JP => 'Durasi pertemuan (menit) dibagi angka ini lalu dibulatkan jadi jumlah JP. Standar umum: 45 menit = 1 JP.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::INTEGER;
    }

    public function default(): string
    {
        return match ($this) {
            self::MENIT_1_JP => '45',
        };
    }
}
