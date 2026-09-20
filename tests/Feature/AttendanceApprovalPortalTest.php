<?php

namespace Tests\Feature;

use App\Livewire\AttendanceRequestDetail;
use App\Livewire\PendingApprovalsCard;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceApprovalPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequestAwaitingApproval(): array
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
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ], $employee);

        $request = \App\Models\Attendance\AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        return compact('company', 'employee', 'approver', 'request');
    }

    public function test_approver_sees_pending_request_on_pending_approvals_card(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['approver']);

        Livewire::test(PendingApprovalsCard::class)
            ->assertSee($data['employee']->name)
            ->assertSee('Cuti');
    }

    public function test_unrelated_user_cannot_open_detail_page(): void
    {
        $data = $this->makeRequestAwaitingApproval();
        $stranger = User::factory()->create(['company_id' => $data['company']->id]);

        $this->actingAs($stranger)
            ->get(route('my-attendance-requests.show', ['record' => $data['request']->id]))
            ->assertForbidden();
    }

    public function test_submitter_and_approver_can_open_detail_page(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['employee'])
            ->get(route('my-attendance-requests.show', ['record' => $data['request']->id]))
            ->assertOk();

        $this->actingAs($data['approver'])
            ->get(route('my-attendance-requests.show', ['record' => $data['request']->id]))
            ->assertOk();
    }

    public function test_approver_can_approve_request_from_portal(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['approver']);

        Livewire::test(AttendanceRequestDetail::class, ['record' => $data['request']])
            ->assertSet('canApprove', true)
            ->call('chooseAction', 'approve')
            ->set('note', 'Disetujui lewat test otomatis')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', [
            'id' => $data['request']->id,
            'status' => 'approved',
        ]);
    }

    public function test_approver_can_reject_request_from_portal(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['approver']);

        Livewire::test(AttendanceRequestDetail::class, ['record' => $data['request']])
            ->call('chooseAction', 'reject')
            ->set('note', 'Ditolak lewat test otomatis')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', [
            'id' => $data['request']->id,
            'status' => 'rejected',
        ]);
    }

    public function test_approver_can_cancel_request_from_portal(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['approver']);

        Livewire::test(AttendanceRequestDetail::class, ['record' => $data['request']])
            ->call('chooseAction', 'cancel')
            ->set('note', 'Dibatalkan lewat test otomatis')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', [
            'id' => $data['request']->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_submitter_cannot_see_approve_action(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $this->actingAs($data['employee']);

        Livewire::test(AttendanceRequestDetail::class, ['record' => $data['request']])
            ->assertSet('canApprove', false);
    }

    public function test_manager_can_view_subordinates_request_detail_without_being_an_approver(): void
    {
        $data = $this->makeRequestAwaitingApproval();

        $managerPosition = JobPositions::create(['company_id' => $data['company']->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $data['company']->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $data['company']->id]);
        UserJobPositions::create([
            'user_id' => $manager->id,
            'job_position_id' => $managerPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
        UserJobPositions::create([
            'user_id' => $data['employee']->id,
            'job_position_id' => $staffPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->get(route('my-attendance-requests.show', ['record' => $data['request']->id]))
            ->assertOk();

        Livewire::test(AttendanceRequestDetail::class, ['record' => $data['request']])
            ->assertSet('canApprove', false);
    }
}
