<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\User\UserTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Service ini dipakai BERSAMA admin (CreateUserTransition/EditUserTransition) dan portal
 * (Kepegawaian) — tes di sini otomatis memvalidasi kedua jalur sekaligus.
 */
class UserTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeEmployeeWithJobPosition(Companies $company): array
    {
        $oldJob = JobPositions::create(['name' => 'Staff', 'company_id' => $company->id, 'is_draft' => false]);
        $newJob = JobPositions::create(['name' => 'Supervisor', 'company_id' => $company->id, 'is_draft' => false]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $oldJob->id]);

        return [$employee, $oldJob, $newJob];
    }

    public function test_create_persists_transition_with_number_and_pending_status(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        [$employee, , $newJob] = $this->makeEmployeeWithJobPosition($company);

        $transition = app(UserTransitionService::class)->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
                'transitionable_id' => $newJob->id,
                'effective_date' => now()->addWeek()->toDateString(),
            ],
            detailData: ['target_achievement' => 'Memenuhi target sepenuhnya'],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $this->assertNotNull($transition->number);
        $this->assertSame(UserTransitionStatusEnum::PENDING->value, $transition->status);
        $this->assertSame($company->id, $transition->company_id);
        $this->assertSame('Staff', $transition->old_value);
        $this->assertNotNull($transition->approvalSnapshot);
        $this->assertDatabaseHas('user_transition_details', [
            'user_transition_id' => $transition->id,
            'target_achievement' => 'Memenuhi target sepenuhnya',
        ]);
    }

    public function test_create_without_approval_flow_throws_exception(): void
    {
        $company = Companies::create(['code' => 'NOF', 'name' => 'No Flow Co', 'location' => 'Test', 'is_active' => true]);
        $hc = User::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->expectException(\Exception::class);

        app(UserTransitionService::class)->create(
            data: ['user_id' => $employee->id, 'transition_type' => UserTransitionTypeEnum::TERMINATION->value, 'reason' => 'Test'],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );
    }

    public function test_approve_promotion_changes_job_position_and_profile(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        [$employee, $oldJob, $newJob] = $this->makeEmployeeWithJobPosition($company);

        $employee->jobPositions()->create(['job_position_id' => $oldJob->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $service = app(UserTransitionService::class);
        $transition = $service->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
                'transitionable_id' => $newJob->id,
                'effective_date' => now()->toDateString(),
            ],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $approver = $transition->approvalSnapshot->steps_snapshot[0]['approver_ref_id'] ?? null;

        $service->approve($transition, $approver);

        $transition->refresh();
        $employee->refresh();

        $this->assertSame(UserTransitionStatusEnum::APPROVED->value, $transition->status);
        $this->assertNotNull($transition->approved_at);
        $this->assertSame($newJob->id, $employee->profile->job_position_id);
        $this->assertDatabaseHas('user_job_positions', [
            'user_id' => $employee->id,
            'job_position_id' => $oldJob->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('user_job_positions', [
            'user_id' => $employee->id,
            'job_position_id' => $newJob->id,
            'status' => 'active',
        ]);
    }

    public function test_approve_termination_deactivates_user(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $employee->id]);

        $service = app(UserTransitionService::class);
        $transition = $service->create(
            data: ['user_id' => $employee->id, 'transition_type' => UserTransitionTypeEnum::TERMINATION->value, 'reason' => 'Mengundurkan diri'],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $approver = $transition->approvalSnapshot->steps_snapshot[0]['approver_ref_id'] ?? null;
        $service->approve($transition, $approver);

        $employee->refresh();

        $this->assertFalse((bool) $employee->is_active);
    }

    public function test_approve_mutation_changes_job_position(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        [$employee, $oldJob, $newJob] = $this->makeEmployeeWithJobPosition($company);
        $employee->jobPositions()->create(['job_position_id' => $oldJob->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $service = app(UserTransitionService::class);
        $transition = $service->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::MUTATION->value,
                'transitionable_id' => $newJob->id,
                'effective_date' => now()->toDateString(),
            ],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $approver = $transition->approvalSnapshot->steps_snapshot[0]['approver_ref_id'] ?? null;
        $service->approve($transition, $approver);

        $this->assertSame($newJob->id, $employee->profile->fresh()->job_position_id);
        $this->assertDatabaseHas('user_job_positions', [
            'user_id' => $employee->id,
            'job_position_id' => $newJob->id,
            'status' => 'active',
        ]);
    }

    public function test_approve_with_future_effective_date_defers_application(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        [$employee, $oldJob, $newJob] = $this->makeEmployeeWithJobPosition($company);
        $employee->jobPositions()->create(['job_position_id' => $oldJob->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $service = app(UserTransitionService::class);
        $transition = $service->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
                'transitionable_id' => $newJob->id,
                'effective_date' => now()->addWeek()->toDateString(),
            ],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $approver = $transition->approvalSnapshot->steps_snapshot[0]['approver_ref_id'] ?? null;
        $service->approve($transition, $approver);

        $transition->refresh();

        // Disetujui, tapi efek ke karyawan BELUM diterapkan (nunggu tanggal efektif).
        $this->assertSame(UserTransitionStatusEnum::APPROVED->value, $transition->status);
        $this->assertNull($transition->applied_at);
        $this->assertSame($oldJob->id, $employee->profile->fresh()->job_position_id);

        // Command jalan hari ini -> belum due, tidak terjadi apa-apa.
        $this->assertSame(['applied' => 0, 'failed' => 0], $service->applyDueTransitions());
        $this->assertNull($transition->fresh()->applied_at);

        // Tanggal efektif tiba -> command menerapkan.
        $this->travelTo(now()->addWeek());
        $result = $service->applyDueTransitions();
        $this->travelBack();

        $this->assertSame(['applied' => 1, 'failed' => 0], $result);
        $this->assertNotNull($transition->fresh()->applied_at);
        $this->assertSame($newJob->id, $employee->profile->fresh()->job_position_id);
    }

    public function test_reject_sets_status_without_changing_employee_data(): void
    {
        $company = $this->makeCompanyWithApprovalFlow();
        $hc = User::factory()->create(['company_id' => $company->id]);
        [$employee, $oldJob, $newJob] = $this->makeEmployeeWithJobPosition($company);

        $service = app(UserTransitionService::class);
        $transition = $service->create(
            data: [
                'user_id' => $employee->id,
                'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
                'transitionable_id' => $newJob->id,
                'effective_date' => now()->toDateString(),
            ],
            detailData: [],
            attachmentPaths: [],
            actingUser: $hc,
        );

        $approver = $transition->approvalSnapshot->steps_snapshot[0]['approver_ref_id'] ?? null;
        $service->reject($transition, $approver);

        $transition->refresh();

        $this->assertSame(UserTransitionStatusEnum::REJECTED->value, $transition->status);
        $this->assertSame($oldJob->id, $employee->profile->fresh()->job_position_id);
    }

}
