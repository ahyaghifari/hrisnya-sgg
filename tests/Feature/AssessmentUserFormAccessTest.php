<?php

namespace Tests\Feature;

use App\Livewire\AssessmentUserForm;
use App\Models\Assessment\AssessmentTemplateItems;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\Assessments;
use App\Models\Assessment\AssessmentTypes;
use App\Models\Assessment\AssessmentUsers;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guard akses /penilaian/{id} — lihat planning/2026-08-01-portal-penilaian-karyawan.md §6.
 */
class AssessmentUserFormAccessTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    private function makeAssessmentUser(Companies $company, User $target): AssessmentUsers
    {
        $type = AssessmentTypes::create(['name' => 'Tahunan']);
        $template = AssessmentTemplates::create(['company_id' => $company->id, 'type_id' => $type->id, 'name' => 'Template Tahunan']);
        AssessmentTemplateItems::create(['template_id' => $template->id, 'sort_order' => 1, 'name' => 'Kedisiplinan', 'weight_percent' => 100, 'max_score' => 100]);
        $assessment = Assessments::create(['company_id' => $company->id, 'template_id' => $template->id, 'name' => 'Penilaian 2026', 'status' => 'active']);

        return AssessmentUsers::create(['assessment_id' => $assessment->id, 'target_id' => $target->id, 'created_by' => $target->id]);
    }

    public function test_target_karyawan_can_view_own_assessment(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $assessmentUser = $this->makeAssessmentUser($company, $staff);

        $this->actingAs($staff);

        Livewire::test(AssessmentUserForm::class, ['assessmentUser' => $assessmentUser])
            ->assertOk()
            ->assertSet('canEdit', false);
    }

    public function test_direct_manager_can_edit_subordinate_assessment(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $assessmentUser = $this->makeAssessmentUser($company, $staff);

        $this->actingAs($manager);

        Livewire::test(AssessmentUserForm::class, ['assessmentUser' => $assessmentUser])
            ->assertOk()
            ->assertSet('canEdit', true);
    }

    public function test_unrelated_manager_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $otherManagerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager Lain', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $otherManager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($otherManager, $otherManagerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $assessmentUser = $this->makeAssessmentUser($company, $staff);

        $this->actingAs($otherManager);

        Livewire::test(AssessmentUserForm::class, ['assessmentUser' => $assessmentUser])
            ->assertForbidden();
    }
}
