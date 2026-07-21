<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\UserTransitionTypeEnum;
use App\Livewire\Kepegawaian\CreateUserTransitionPage;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Models\User\UserTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateUserTransitionPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHc(string $companyId): User
    {
        Permission::firstOrCreate(['name' => 'Create:UserTransition', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'HC Create UserTransition Test', 'guard_name' => 'web']);
        $role->givePermissionTo('Create:UserTransition');

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompanyWithApprovalFlow(): Companies
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'user_transition',
            'name' => 'Alur Test',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        return $company;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)->test(CreateUserTransitionPage::class)->assertForbidden();
    }

    public function test_submits_promotion_and_redirects_to_detail(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = $this->actingAsHc($company->id);
        $job = JobPositions::create(['name' => 'Supervisor', 'company_id' => $company->id, 'is_draft' => false]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id]);

        Livewire::actingAs($hc)
            ->test(CreateUserTransitionPage::class)
            ->set('user_id', $employee->id)
            ->set('transition_type', UserTransitionTypeEnum::PROMOTION->value)
            ->set('transitionable_id', $job->id)
            ->set('effective_date', now()->addWeek()->toDateString())
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('user_transitions', [
            'user_id' => $employee->id,
            'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
            'transitionable_id' => $job->id,
            'status' => 'pending',
        ]);
    }

    public function test_type_query_param_preselects_transition_type(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = $this->actingAsHc($company->id);

        $response = $this->actingAs($hc)->get(route('kepegawaian.mutasi-karyawan.create', ['type' => UserTransitionTypeEnum::TERMINATION->value]));

        $response->assertOk();
        // Section "Alasan" (khusus PHK) cuma tampil kalau transition_type ke-preselect benar.
        $response->assertSee('Tuliskan alasan PHK');
    }

    public function test_termination_requires_reason(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = $this->actingAsHc($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($hc)
            ->test(CreateUserTransitionPage::class)
            ->set('user_id', $employee->id)
            ->set('transition_type', UserTransitionTypeEnum::TERMINATION->value)
            ->call('submit')
            ->assertHasErrors(['reason']);

        $this->assertSame(0, UserTransition::count());
    }
}
