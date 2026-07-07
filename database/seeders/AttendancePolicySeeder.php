<?php

namespace Database\Seeders;

use App\Models\Attendance\AttendancePolicies;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendancePolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AttendancePolicies::make([
            'company_id' => null,
            'name' => 'Minimum Total Jam Kerja Sebulan',
            'type' => 'min_total_working_hours_per_month',
            'value' => 170
        ])->save();
    }
}
