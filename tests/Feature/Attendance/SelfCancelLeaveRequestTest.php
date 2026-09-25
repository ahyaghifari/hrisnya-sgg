<?php

namespace Tests\Feature\Attendance;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pemilik pengajuan (bukan approver) boleh membatalkan CUTI/IZIN miliknya sendiri selama masih
 * in_review dan belum lewat tanggal mulai (H) - lihat
 * AttendanceRequestDetail::getCanSelfCancelProperty()/confirmSelfAction().
 */
class SelfCancelLeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

        $approver = User::factory()->create(['company_id' => $this->company->id]);
        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
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
    }

    private function submitLeave(User $employee, string $start): AttendanceRequests
    {
        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'is_one_day' => true,
            'start_date' => $start,
            'end_date' => $start,
        ], $employee);

        return AttendanceRequests::where('user_id', $employee->id)->firstOrFail();
    }

    public function test_owner_can_self_cancel_while_in_review(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->submitLeave($employee, now()->addDays(3)->toDateString());

        $this->actingAs($employee);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('canSelfCancel', true)
            ->call('chooseSelfAction', 'self_cancel')
            ->set('selfActionReason', 'Batal, jadwal berubah')
            ->call('confirmSelfAction')
            ->assertSet('canSelfCancel', false);

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'cancelled']);
    }

    public function test_self_cancel_button_hidden_and_blocked_after_cutoff(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->submitLeave($employee, now()->subDay()->toDateString());

        $this->actingAs($employee);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('canSelfCancel', false)
            ->call('chooseSelfAction', 'self_cancel')
            ->set('selfActionReason', 'Coba batal telat')
            ->call('confirmSelfAction')
            ->assertForbidden();

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'in_review']);
    }

    public function test_other_user_cannot_self_cancel_someone_elses_request(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->submitLeave($employee, now()->addDays(3)->toDateString());

        $this->actingAs($otherUser);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertForbidden();
    }
}
