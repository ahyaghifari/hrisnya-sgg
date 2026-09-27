<?php

namespace Database\Seeders;

use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Models\Master\Companies;
use App\Models\MeritPoint\MeritPointRule;
use Illuminate\Database\Seeder;

/**
 * Seed DEMO merit_point_rules (planning/2026-09-23-sp-dan-merit-point.md §3.3) - cuma untuk 1
 * company contoh (company pertama yang ada), BUKAN disebar ke semua company (§3.1: opt-in per
 * company, default MERIT_POINT_ENABLED=false). Jalankan manual:
 * php artisan db:seed --class=MeritPointDemoSeeder
 */
class MeritPointDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Companies::query()->orderBy('created_at')->first();

        if (! $company) {
            $this->command?->warn('Tidak ada company - lewati seed demo Merit Point.');

            return;
        }

        $rules = [
            ['name' => '[DEMO] Datang Tepat Waktu 1 Bulan Penuh', 'type' => MeritPointType::REWARD, 'points' => 5],
            ['name' => '[DEMO] Ide Inovasi Diterima', 'type' => MeritPointType::REWARD, 'points' => 10],
            ['name' => '[DEMO] Bantu Rekan Kerja di Luar Tugas', 'type' => MeritPointType::REWARD, 'points' => 3],
            ['name' => '[DEMO] Terlambat Tanpa Keterangan', 'type' => MeritPointType::PENALTY, 'points' => -5],
            ['name' => '[DEMO] Melanggar SOP Ringan', 'type' => MeritPointType::PENALTY, 'points' => -10],
        ];

        foreach ($rules as $rule) {
            MeritPointRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => $rule['name']],
                [
                    'type' => $rule['type']->value,
                    'points' => $rule['points'],
                    'trigger_type' => MeritPointTriggerType::MANUAL->value,
                    'is_active' => true,
                ]
            );
        }
    }
}
