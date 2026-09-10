<?php

namespace Tests\Feature;

use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSupervisorStepTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private JobPositions $managerPosition;
    private JobPositions $staffPosition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create([
            'code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true,
        ]);

        $this->managerPosition = JobPositions::create([
            'company_id' => $this->company->id, 'name' => 'Manager', 'planned_manpower' => 1,
        ]);
        $this->staffPosition = JobPositions::create([
            'company_id' => $this->company->id, 'name' => 'Staff', 'planned_manpower' => 1,
            'parent_id' => $this->managerPosition->id,
        ]);
    }

    private function makeSupervisorFlow(int $level = 1): MasterApprovalFlows
    {
        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'attendance_request',
            'name' => 'Alur Atasan',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'supervisor',
            'approver_ref_id' => null,
            'supervisor_level' => $level,
            'approval_mode' => 'any',
            'scope_type' => 'same_department',
            'is_final' => true,
        ]);

        return $flow->load('steps');
    }

    public function test_supervisor_step_frozen_to_parent_job_position_on_snapshot(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $this->staffPosition->id]);

        $flow = $this->makeSupervisorFlow();

        app(ApprovalCreationService::class)->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: 'req-1',
            flow: $flow,
            createdBy: $employee->id,
            companyId: $this->company->id,
            userId: $employee->id,
        );

        $step = ApprovalSnapshots::where('module_id', 'req-1')->value('steps_snapshot')[0];

        $this->assertSame('job_position', $step['approver_type']);
        $this->assertSame($this->managerPosition->id, $step['approver_ref_id']);
    }

    public function test_supervisor_level_2_walks_two_positions_up(): void
    {
        $instalasiPosition = JobPositions::create([
            'company_id' => $this->company->id, 'name' => 'Kepala Instalasi', 'planned_manpower' => 1,
        ]);
        $this->managerPosition->update(['parent_id' => $instalasiPosition->id]);

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $this->staffPosition->id]);

        $flow = $this->makeSupervisorFlow(2);

        app(ApprovalCreationService::class)->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: 'req-lvl2',
            flow: $flow,
            createdBy: $employee->id,
            companyId: $this->company->id,
            userId: $employee->id,
        );

        $step = ApprovalSnapshots::where('module_id', 'req-lvl2')->value('steps_snapshot')[0];

        $this->assertSame('job_position', $step['approver_type']);
        $this->assertSame($instalasiPosition->id, $step['approver_ref_id']);
    }

    public function test_submit_rejected_when_requester_has_no_job_position(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => null]);

        $flow = $this->makeSupervisorFlow();

        $this->expectException(\RuntimeException::class);

        app(ApprovalCreationService::class)->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: 'req-2',
            flow: $flow,
            createdBy: $employee->id,
            companyId: $this->company->id,
            userId: $employee->id,
        );
    }

    public function test_submit_rejected_when_requester_job_position_has_no_parent(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        // Jabatan paling atas — tidak punya atasan.
        UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $this->managerPosition->id]);

        $flow = $this->makeSupervisorFlow();

        $this->expectException(\RuntimeException::class);

        app(ApprovalCreationService::class)->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: 'req-3',
            flow: $flow,
            createdBy: $employee->id,
            companyId: $this->company->id,
            userId: $employee->id,
        );
    }
}
