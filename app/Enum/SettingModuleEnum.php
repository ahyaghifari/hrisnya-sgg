<?php

namespace App\Enum;

/**
 * Pengelompokan `settings` per modul — cuma buat tampilan/filter admin, BUKAN dipakai
 * Setting::resolve() (key tetap unik & stabil lintas modul). Tambah case baru kalau ada
 * modul lain yang butuh config (mis. 'kanban', 'lms').
 */
enum SettingModuleEnum: string
{
    case PAYROLL = 'payroll';
    case ATTENDANCE = 'attendance';

    public function label(): string
    {
        return match ($this) {
            self::PAYROLL => 'Payroll',
            self::ATTENDANCE => 'Absensi',
        };
    }
}
