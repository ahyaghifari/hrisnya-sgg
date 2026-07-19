<?php

namespace Database\Seeders;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Master\Companies;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Baris awal `settings` module `attendance` — dipisah dari PayrollSettingsSeeder (nama itu
 * menyesatkan untuk data non-payroll, lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §5).
 * Idempotent lewat firstOrCreate, sengaja tidak dipanggil dari DatabaseSeeder:
 *   php artisan db:seed --class=AttendanceSettingsSeeder
 */
class AttendanceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AttendanceSettingEnum::cases() as $case) {
            Setting::firstOrCreate(
                ['company_id' => null, 'key' => $case->value],
                [
                    'module' => 'attendance',
                    'type' => $case->type()->value,
                    'value' => $case->default(),
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                ]
            );
        }

        // Override khusus Holding — dipindah dari PayrollSettingsSeeder (data sama, sumber baru).
        $holdingId = Companies::where('code', '0000')->value('id');

        if ($holdingId) {
            Setting::updateOrCreate(
                ['company_id' => $holdingId, 'key' => AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->value],
                [
                    'module' => 'attendance',
                    'type' => AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->type()->value,
                    'value' => '5',
                    'label' => AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->label(),
                    'description' => 'Override khusus Holding: maksimal 5 hari Cuti Tahunan (akumulasi) per periode per user.',
                ]
            );
        }
    }
}
