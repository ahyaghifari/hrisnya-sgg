<?php

namespace Tests\Feature;

use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRequestCancelTest extends TestCase
{
    use RefreshDatabase;

    /** Lembur: pemilik boleh batal sendiri sampai hari H (in_review) atau ajukan pembatalan (approved). */
    public function test_owner_can_cancel_overtime_until_its_date(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id, 'module' => 'attendance_request', 'name' => 'Alur Test',
            'flow_type' => 'default', 'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user', 'approver_ref_id' => $approver->id,
            'approval_mode' => 'any', 'scope_type' => 'global', 'is_final' => true,
        ]);

        $submit = fn (string $date) => app(AttendaceReqService::class)->storeOvertimeRequest([
            'type_id' => 'biasa', 'date' => $date, 'start_time' => '17:00', 'end_time' => '19:00', 'task_description' => 'Tugas',
        ], $employee);

        $submit(now()->addDay()->toDateString());
        $future = \App\Models\Attendance\AttendanceRequests::where('user_id', $employee->id)->latest('id')->firstOrFail();
        $submit(now()->subDay()->toDateString());
        $past = \App\Models\Attendance\AttendanceRequests::where('user_id', $employee->id)->whereKeyNot($future->id)->firstOrFail();

        $page = fn ($record) => \Livewire\Livewire::actingAs($employee)->test(\App\Livewire\AttendanceRequestDetail::class, ['record' => $record]);

        $this->assertTrue($page($future)->instance()->canSelfCancel);
        $this->assertFalse($page($past)->instance()->canSelfCancel); // tanggal lembur sudah lewat

        // Batal langsung (masih in_review).
        $page($future)->set('selfAction', 'self_cancel')->set('selfActionReason', 'Tidak jadi')->call('confirmSelfAction');
        $this->assertSame('cancelled', $future->fresh()->status);
        $this->assertSame($employee->id, $future->fresh()->cancelled_by);
    }

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

        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $leaveType->value,
            'is_one_day' => true,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
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
