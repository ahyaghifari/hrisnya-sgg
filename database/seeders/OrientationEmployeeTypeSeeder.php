<?php

namespace Database\Seeders;

use App\Enum\EmployeeTypeCategory;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use Illuminate\Database\Seeder;

/**
 * Pastikan tiap company punya SATU tipe karyawan berkategori 'orientasi' - dipakai
 * CandidateOnboardingService buat men-set employee_type_id karyawan baru dari Rekrutmen.
 * Idempotent: skip company yang sudah punya.
 */
class OrientationEmployeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Companies::all() as $company) {
            $exists = EmployeeTypes::where('company_id', $company->id)
                ->where('category', EmployeeTypeCategory::ORIENTASI->value)
                ->exists();

            if ($exists) {
                continue;
            }

            EmployeeTypes::create([
                'name' => 'Orientasi',
                'category' => EmployeeTypeCategory::ORIENTASI->value,
                'company_id' => $company->id,
            ]);
        }
    }
}
