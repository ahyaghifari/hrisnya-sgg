<?php

namespace Tests\Feature\Presensi;

use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use Database\Seeders\LeaveTypeCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_four_categories_and_backfills_existing(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

        // Baris existing tanpa category — harus di-backfill, bukan dobel.
        LeaveTypes::create(['company_id' => $company->id, 'name' => 'Cuti Tahunan', 'quota_days' => 15, 'reset_period' => 'yearly', 'is_paid' => true]);

        $this->seed(LeaveTypeCategorySeeder::class);

        $this->assertSame(1, LeaveTypes::where('company_id', $company->id)->where('name', 'Cuti Tahunan')->count());
        $this->assertSame('tahunan', LeaveTypes::where('company_id', $company->id)->where('name', 'Cuti Tahunan')->value('category'));
        $this->assertSame(15, LeaveTypes::where('company_id', $company->id)->where('name', 'Cuti Tahunan')->value('quota_days')); // kuota lama dipertahankan

        $this->assertEqualsCanonicalizing(
            ['tahunan', 'haid', 'melahirkan', 'keguguran'],
            LeaveTypes::where('company_id', $company->id)->pluck('category')->all()
        );

        // Idempoten.
        $this->seed(LeaveTypeCategorySeeder::class);
        $this->assertSame(4, LeaveTypes::where('company_id', $company->id)->count());
    }
}
