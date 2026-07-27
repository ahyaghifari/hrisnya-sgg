<?php

namespace Tests\Feature;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filter "Tipe Karyawan" (is_intern) di halaman admin Karyawan (EmployeesTable) - satu
 * TernaryFilter (Semua/Hanya Magang/Hanya Karyawan), bukan 2 checkbox terpisah, dibedakan lewat
 * jabatan yang ditandai is_internship (sama penanda yang dipakai InternEmployeeResource).
 */
class EmployeesTableInternFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsGlobalAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']));
        $this->actingAs($admin);
    }

    public function test_filter_narrows_to_interns_or_regular_employees(): void
    {
        $company = Companies::create(['code' => 'ETF', 'name' => 'Company ETF', 'location' => 'Test', 'is_active' => true]);

        $internPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Magang IT', 'is_internship' => true,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);
        $regularPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Staff IT', 'is_internship' => false,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $intern = User::factory()->create(['company_id' => $company->id, 'name' => 'Intern Budi']);
        UserProfiles::create(['user_id' => $intern->id, 'job_position_id' => $internPosition->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Staff Ani']);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $regularPosition->id]);

        $this->actingAsGlobalAdmin();

        Livewire::test(ListEmployees::class)
            ->filterTable('is_intern', true)
            ->assertCanSeeTableRecords([$intern])
            ->assertCanNotSeeTableRecords([$employee]);

        Livewire::test(ListEmployees::class)
            ->filterTable('is_intern', false)
            ->assertCanSeeTableRecords([$employee])
            ->assertCanNotSeeTableRecords([$intern]);
    }

    public function test_filter_defaults_to_employees_only(): void
    {
        $company = Companies::create(['code' => 'ETF2', 'name' => 'Company ETF2', 'location' => 'Test', 'is_active' => true]);

        $internPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Magang IT', 'is_internship' => true,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $intern = User::factory()->create(['company_id' => $company->id, 'name' => 'Intern Budi']);
        UserProfiles::create(['user_id' => $intern->id, 'job_position_id' => $internPosition->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Staff Ani']);

        $this->actingAsGlobalAdmin();

        // Belum pilih filter apa pun - default-nya sendiri sudah "Hanya Karyawan".
        Livewire::test(ListEmployees::class)
            ->assertCanSeeTableRecords([$employee])
            ->assertCanNotSeeTableRecords([$intern]);
    }

    public function test_filter_hidden_when_company_has_no_interns(): void
    {
        $company = Companies::create(['code' => 'ETF3', 'name' => 'Company ETF3', 'location' => 'Test', 'is_active' => true]);
        $regularPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Staff IT', 'is_internship' => false,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $regularPosition->id]);

        $this->actingAsGlobalAdmin();

        Livewire::test(ListEmployees::class)
            ->assertTableFilterHidden('is_intern');
    }

    public function test_filter_visible_when_company_has_interns(): void
    {
        $company = Companies::create(['code' => 'ETF4', 'name' => 'Company ETF4', 'location' => 'Test', 'is_active' => true]);
        $internPosition = JobPositions::create([
            'company_id' => $company->id, 'name' => 'Magang IT', 'is_internship' => true,
            'is_draft' => false, 'status' => 'approved', 'actual_manpower' => 5,
        ]);

        $intern = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $intern->id, 'job_position_id' => $internPosition->id]);

        $this->actingAsGlobalAdmin();

        Livewire::test(ListEmployees::class)
            ->assertTableFilterVisible('is_intern');
    }
}
