<?php

namespace Tests\Feature;

use App\Filament\Resources\Internship\InternEmployees\InternEmployeeResource;
use App\Filament\Resources\Internship\InternshipPositions\InternshipPositionResource;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InternshipPositionResource/InternEmployeeResource TIDAK menambah tabel/model baru - keduanya
 * cuma reuse m_job_positions (ditandai is_internship) dan User biasa, dibedakan lewat scope query
 * masing-masing resource. Test ini memastikan scope-nya benar: cuma posisi/karyawan magang yang
 * muncul, bukan seluruh m_job_positions/users perusahaan.
 */
class InternshipResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsGlobalAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\App\Models\Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']));
        $this->actingAs($admin);
    }

    public function test_internship_position_resource_only_lists_positions_flagged_as_internship(): void
    {
        $this->actingAsGlobalAdmin();
        $company = Companies::create(['code' => 'CIP', 'name' => 'Company IP', 'location' => 'Test', 'is_active' => true]);

        $internPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Magang IT', 'is_internship' => true,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);
        $regularPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Staff IT', 'is_internship' => false,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $ids = InternshipPositionResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($internPosition->id));
        $this->assertFalse($ids->contains($regularPosition->id));
    }

    public function test_intern_employee_resource_only_lists_users_on_an_internship_position(): void
    {
        $this->actingAsGlobalAdmin();
        $company = Companies::create(['code' => 'CIE', 'name' => 'Company IE', 'location' => 'Test', 'is_active' => true]);

        $internPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Magang HR', 'is_internship' => true,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);
        $regularPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'HR Staff', 'is_internship' => false,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $intern = User::factory()->create(['company_id' => $company->id, 'nip' => 'MAG001']);
        UserProfiles::create(['user_id' => $intern->id, 'job_position_id' => $internPosition->id]);

        $regularEmployee = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP999']);
        UserProfiles::create(['user_id' => $regularEmployee->id, 'job_position_id' => $regularPosition->id]);

        $ids = InternEmployeeResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($intern->id));
        $this->assertFalse($ids->contains($regularEmployee->id));
    }
}
