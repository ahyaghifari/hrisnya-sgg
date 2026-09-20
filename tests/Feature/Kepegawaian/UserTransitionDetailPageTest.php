<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\UserTransitionTypeEnum;
use App\Livewire\Kepegawaian\UserTransitionDetailPage;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\User\UserTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTransitionDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermission(string $companyId, string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => "Test {$permission}", 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTransitionWithApprover(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        Permission::firstOrCreate(['name' => 'View:UserTransition', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:UserTransition', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Approver UserTransition Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['View:UserTransition', 'Update:UserTransition']);
        $approver->assignRole($role);

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

        $hc = User::factory()->create(['company_id' => $company->id]);
        $job = JobPositions::create(['name' => 'Supervisor', 'company_id' => $company->id, 'status' => 'approved']);
        $employee = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id]);

        $transition = app(UserTransitionService::class)->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
                'transitionable_id' => $job->id,
                'effective_date' => now()->toDateString(),
            ],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        return [$company, $transition, $approver, $employee, $job];
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $transition = \App\Models\User\UserTransition::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'created_by' => $user->id,
            'number' => 'X/1',
            'status' => 'pending',
            'transition_type' => UserTransitionTypeEnum::TERMINATION->value,
        ]);

        Livewire::actingAs($user)->test(UserTransitionDetailPage::class, ['record' => $transition])->assertForbidden();
    }

    public function test_approver_sees_approve_action_and_can_approve(): void
    {
        [, $transition, $approver, $employee, $job] = $this->makeTransitionWithApprover();

        Livewire::actingAs($approver)
            ->test(UserTransitionDetailPage::class, ['record' => $transition])
            ->assertSet('canApprove', true)
            ->call('approve');

        $transition->refresh();
        $this->assertSame('approved', $transition->status);
        $this->assertSame($job->id, $employee->profile->fresh()->job_position_id);
    }

    public function test_non_approver_does_not_see_approve_action(): void
    {
        [$company, $transition] = $this->makeTransitionWithApprover();
        $stranger = $this->actingAsWithPermission($company->id, 'View:UserTransition');

        Livewire::actingAs($stranger)
            ->test(UserTransitionDetailPage::class, ['record' => $transition])
            ->assertSet('canApprove', false);
    }

    public function test_reject_sets_status_rejected(): void
    {
        [, $transition, $approver, $employee, $job] = $this->makeTransitionWithApprover();

        Livewire::actingAs($approver)
            ->test(UserTransitionDetailPage::class, ['record' => $transition])
            ->call('reject');

        $transition->refresh();
        $this->assertSame('rejected', $transition->status);
        $this->assertNull($employee->profile->fresh()->job_position_id);
    }
}
