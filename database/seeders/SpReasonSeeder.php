<?php

namespace Database\Seeders;

use App\Models\Sp\SpReason;
use Illuminate\Database\Seeder;

/**
 * Seed awal sp_reasons (planning/2026-09-23-sp-dan-merit-point.md §2.2, persis dokumen acuan §1.2).
 * Jalankan manual: php artisan db:seed --class=SpReasonSeeder
 */
class SpReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'LATE_ATTENDANCE', 'name' => 'Keterlambatan'],
            ['code' => 'UNAUTHORIZED_ABSENCE', 'name' => 'Absen Tanpa Keterangan'],
            ['code' => 'NOT_FOLLOWING_INSTRUCTION', 'name' => 'Tidak Mengikuti Instruksi'],
            ['code' => 'SOP_VIOLATION', 'name' => 'Pelanggaran SOP'],
            ['code' => 'MISCONDUCT', 'name' => 'Perilaku Tidak Pantas'],
        ];

        foreach ($reasons as $reason) {
            SpReason::firstOrCreate(['code' => $reason['code']], $reason);
        }
    }
}
