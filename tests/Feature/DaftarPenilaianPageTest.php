<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Livewire\AssessmentUserForm;
use App\Livewire\Assessment\CreatePenilaianKaryawanPage;
use App\Livewire\Assessment\DaftarPenilaianPage;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\AssessmentTypes;
use App\Models\Assessment\Assessments;
use App\Models\Assessment\AssessmentUsers;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DaftarPenilaianPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHc(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Penilaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeActiveAssessment(Companies $company): Assessments
    {
        $type = AssessmentTypes::create(['name' => 'Tahunan']);
        $template = AssessmentTemplates::create(['company_id' => $company->id, 'type_id' => $type->id, 'name' => 'Template Uji']);

        return Assessments::create([
            'company_id' => $company->id,
            'template_id' => $template->id,
            'name' => 'Penilaian Uji',
            'status' => 'active',
        ]);
    }

    public function test_user_without_permission_is_forbidden_from_daftar(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(DaftarPenilaianPage::class)->assertForbidden();
    }

    public function test_hc_can_assign_employee_to_active_jadwal(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hc = $this->actingAsHc($company->id);
        $assessment = $this->makeActiveAssessment($company);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($hc);

        Livewire::test(CreatePenilaianKaryawanPage::class)
            ->set('assessment_id', $assessment->id)
            ->set('target_id', $employee->id)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertTrue(
            AssessmentUsers::where('assessment_id', $assessment->id)->where('target_id', $employee->id)->exists()
        );
    }

    public function test_hc_can_view_but_not_edit_assigned_record(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hc = $this->actingAsHc($company->id);
        $assessment = $this->makeActiveAssessment($company);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $assessmentUser = AssessmentUsers::create([
            'assessment_id' => $assessment->id,
            'target_id' => $employee->id,
            'created_by' => $hc->id,
        ]);

        $this->actingAs($hc);

        Livewire::test(AssessmentUserForm::class, ['assessmentUser' => $assessmentUser])
            ->assertOk()
            ->assertSet('canEdit', false);
    }

    public function test_stranger_without_permission_cannot_view_others_record(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $assessment = $this->makeActiveAssessment($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);

        $assessmentUser = AssessmentUsers::create([
            'assessment_id' => $assessment->id,
            'target_id' => $employee->id,
            'created_by' => $employee->id,
        ]);

        $this->actingAs($stranger);

        Livewire::test(AssessmentUserForm::class, ['assessmentUser' => $assessmentUser])
            ->assertForbidden();
    }
}
