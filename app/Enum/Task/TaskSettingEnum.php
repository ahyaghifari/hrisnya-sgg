<?php

namespace App\Enum\Task;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `task` — pola sama
 * App\Enum\Attendance\AttendanceSettingEnum. Company-scoped, dibaca lewat
 * Setting::resolve($case->value, $companyId, default: ...).
 */
enum TaskSettingEnum: string
{
    case REQUIRE_APPROVAL_DEFAULT = 'task_require_approval_default';

    public function label(): string
    {
        return match ($this) {
            self::REQUIRE_APPROVAL_DEFAULT => 'Tugas Butuh Approval secara Default',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::REQUIRE_APPROVAL_DEFAULT => 'Kalau aktif, form buat tugas baru akan menyalakan toggle "Butuh Approval" secara default (tetap bisa diubah per tugas oleh pembuatnya).',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::BOOLEAN;
    }

    public function default(): string
    {
        return match ($this) {
            self::REQUIRE_APPROVAL_DEFAULT => '0',
        };
    }
}
