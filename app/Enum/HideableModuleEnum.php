<?php

namespace App\Enum;

/**
 * Daftar modul/resource yang bisa disembunyikan per company lewat halaman Filament
 * "Visibilitas Modul" (App\Filament\Pages\ModuleVisibilitySettings) — dipakai untuk modul yang
 * cuma relevan buat sebagian company (mis. Extra Shift cuma dipakai RS, tidak dipakai Holding).
 * Disimpan sebagai baris `settings` (module 'system', key = $case->value, type boolean),
 * dibaca lewat Setting::resolve($case->value, $companyId, true). Tambah case baru di sini kalau
 * ada modul lain yang perlu bisa disembunyikan, lalu cek Setting::resolve(...) di
 * shouldRegisterNavigation()/canAccess() Resource/Page terkait.
 */
enum HideableModuleEnum: string
{
    case EXTRA_SHIFT = 'module_extra_shift_enabled';

    public function label(): string
    {
        return match ($this) {
            self::EXTRA_SHIFT => 'Extra Shift',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EXTRA_SHIFT => 'Penugasan shift tambahan untuk karyawan berjadwal shift — biasanya cuma dipakai unit RS.',
        };
    }
}
