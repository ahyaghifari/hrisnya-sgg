<?php

namespace Tests\Feature;

use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRequestCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_approver_can_cancel_a_pending_request(): void
    {
        $company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'attendance_request',
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

        $leaveType = LeaveTypes::create([
            'company_id' => $company->id,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $leaveType->id,
            'is_one_day' => true,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ], $employee);

        $request = \App\Models\Attendance\AttendanceRequests::where('user_id', $employee->id)->firstOrFail();
        $request->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $approver->id,
            'Dibatalkan lewat automated test',
            'cancelled'
        );

        $request->refresh();
        $request->approvalSnapshot->refresh();
        $request->approvalSnapshot->activity->refresh();

        $this->assertSame('cancelled', $request->status);
        $this->assertSame('cancelled', $request->approvalSnapshot->status);
        $this->assertSame('cancelled', $request->approvalSnapshot->activity->status);
        $this->assertNotNull($request->cancelled_by);
        $this->assertNotNull($request->cancelled_at);
    }
}
