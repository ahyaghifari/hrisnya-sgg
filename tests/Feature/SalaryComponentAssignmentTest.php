<?php

namespace Tests\Feature;

use App\Filament\Resources\SalaryComponentAssignments\Pages\ViewSalaryComponentAssignment;
use App\Filament\Resources\SalaryComponentAssignments\RelationManagers\AssignmentsRelationManager;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use App\Models\Payroll\SalaryComponentAssignments;
use App\Models\SalaryComponent\SalaryComponents;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaryComponentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function makeComponent(?string $companyId = null, string $category = 'tunjangan'): SalaryComponents
    {
        return SalaryComponents::create([
            'name' => 'Tunjangan Jabatan',
            'category' => $category,
            'calculation_type' => 'fixed',
            'company_id' => $companyId,
            'is_active' => true,
        ]);
    }

    public function test_summary_list_shows_assignment_counts_per_type(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $jobPosition = JobPositions::create(['name' => 'Dokter', 'company_id' => $company->id]);
        $component = $this->makeComponent($company->id);

        $component->assignments()->create([
            'salaryable_type' => Pangkats::class,
            'salaryable_id' => $pangkat->id,
            'amount' => 100000,
        ]);
        $component->assignments()->create([
            'salaryable_type' => JobPositions::class,
            'salaryable_id' => $jobPosition->id,
            'amount' => 200000,
        ]);

        $response = $this->actingAs($admin)->get('/admin/salary-component-assignments');

        $response->assertOk();
        $response->assertSee($company->name);
        $response->assertSee($component->name);
    }

    public function test_can_filter_summary_list_by_company(): void
    {
        $admin = $this->actingAsAdmin();
        $companyA = Companies::create(['code' => 'A', 'name' => 'Rumah Sakit A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'B', 'name' => 'Rumah Sakit B', 'location' => 'Test', 'is_active' => true]);
        $componentA = $this->makeComponent($companyA->id);
        $componentB = $this->makeComponent($companyB->id);

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\SalaryComponentAssignments\Pages\ListSalaryComponentAssignments::class)
            ->filterTable('company_id', $companyA->id)
            ->assertCanSeeTableRecords([$componentA])
            ->assertCanNotSeeTableRecords([$componentB]);
    }

    public function test_view_page_shows_title_and_relation_manager(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $component = $this->makeComponent($company->id);
        $component->assignments()->create([
            'salaryable_type' => Pangkats::class,
            'salaryable_id' => $pangkat->id,
            'amount' => 100000,
        ]);

        $response = $this->actingAs($admin)->get("/admin/salary-component-assignments/{$component->id}");

        $response->assertOk();
        $response->assertSee('Mapping Komponen Gaji');
        $response->assertSee('Perawat');
    }

    public function test_cannot_assign_to_a_pangkat_from_a_different_company(): void
    {
        $admin = $this->actingAsAdmin();
        $companyA = Companies::create(['code' => 'A', 'name' => 'Rumah Sakit A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'B', 'name' => 'Rumah Sakit B', 'location' => 'Test', 'is_active' => true]);
        $pangkatB = Pangkats::create(['name' => 'Perawat B', 'company_id' => $companyB->id]);
        $component = $this->makeComponent($companyA->id);

        // pangkatB bukan opsi valid buat komponen ini (company-nya beda) — submit paksa
        // ID-nya harus ditolak validasi ("Pilih" cuma nge-list pangkat company sendiri).
        Livewire::actingAs($admin)
            ->test(AssignmentsRelationManager::class, [
                'ownerRecord' => $component,
                'pageClass' => ViewSalaryComponentAssignment::class,
            ])
            ->callTableAction('create', data: [
                'salaryable_type' => Pangkats::class,
                'salaryable_id' => $pangkatB->id,
                'amount' => 100000,
            ])
            ->assertHasTableActionErrors(['salaryable_id']);

        $this->assertDatabaseMissing('salary_component_assignments', [
            'salaryable_id' => $pangkatB->id,
        ]);
    }

    public function test_can_create_assignment_for_pangkat(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $component = $this->makeComponent($company->id);

        Livewire::actingAs($admin)
            ->test(AssignmentsRelationManager::class, [
                'ownerRecord' => $component,
                'pageClass' => ViewSalaryComponentAssignment::class,
            ])
            ->callTableAction('create', data: [
                'salaryable_type' => Pangkats::class,
                'salaryable_id' => $pangkat->id,
                'amount' => 500000,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('salary_component_assignments', [
            'salary_component_id' => $component->id,
            'salaryable_type' => Pangkats::class,
            'salaryable_id' => $pangkat->id,
            'amount' => 500000,
        ]);
    }

    public function test_direct_company_assignment_auto_fills_salaryable_id(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $component = $this->makeComponent($company->id, category: 'jasa_pelayanan');

        Livewire::actingAs($admin)
            ->test(AssignmentsRelationManager::class, [
                'ownerRecord' => $component,
                'pageClass' => ViewSalaryComponentAssignment::class,
            ])
            ->callTableAction('create', data: [
                'salaryable_type' => Companies::class,
                'amount' => 1000000,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('salary_component_assignments', [
            'salary_component_id' => $component->id,
            'salaryable_type' => Companies::class,
            'salaryable_id' => $company->id,
            'amount' => 1000000,
        ]);
    }

    public function test_can_edit_assignment_value_but_not_its_target(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $otherPangkat = Pangkats::create(['name' => 'Bidan', 'company_id' => $company->id]);
        $component = $this->makeComponent($company->id);
        $assignment = $component->assignments()->create([
            'salaryable_type' => Pangkats::class,
            'salaryable_id' => $pangkat->id,
            'amount' => 100000,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignmentsRelationManager::class, [
                'ownerRecord' => $component,
                'pageClass' => ViewSalaryComponentAssignment::class,
            ])
            ->callTableAction('edit', $assignment, data: [
                'salaryable_type' => Pangkats::class,
                'salaryable_id' => $otherPangkat->id,
                'amount' => 250000,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('salary_component_assignments', [
            'id' => $assignment->id,
            'salaryable_id' => $pangkat->id,
            'amount' => 250000,
        ]);
    }

    public function test_can_delete_assignment(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $component = $this->makeComponent($company->id);
        $assignment = $component->assignments()->create([
            'salaryable_type' => Pangkats::class,
            'salaryable_id' => $pangkat->id,
            'amount' => 100000,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignmentsRelationManager::class, [
                'ownerRecord' => $component,
                'pageClass' => ViewSalaryComponentAssignment::class,
            ])
            ->callTableAction('delete', $assignment);

        $this->assertModelMissing($assignment);
    }
}
