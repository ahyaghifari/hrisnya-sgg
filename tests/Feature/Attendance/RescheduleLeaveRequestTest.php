<?php

namespace Tests\Feature\Attendance;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\ShiftType;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reschedule Cuti (instruksi user 2026-09-28) - pengajuan lama otomatis dibatalkan LANGSUNG
 * (tanpa lewat alur approval pembatalan), baik masih in_review maupun sudah approved (roster/
 * attendance_records ikut direvert), lalu pengajuan baru dibuka dengan tanggal baru. Lihat
 * AttendaceReqService::rescheduleLeaveRequest()/AttendanceRequestDetail::getCanRescheduleProperty().
 */
class RescheduleLeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'attendance_request',
            'name' => 'Alur Cuti',
            'flow_type' => 'default',
            'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->approver->id,
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

    public function test_reschedule_while_in_review_cancels_old_and_opens_new(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $oldStart = now()->addDays(31)->toDateString();
        $newStart = now()->addDays(40)->toDateString();
        $request = $this->submitLeave($employee, $oldStart);

        $this->actingAs($employee);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('canReschedule', true)
            ->call('chooseSelfAction', 'reschedule')
            ->set('rescheduleStart', $newStart)
            ->set('rescheduleEnd', $newStart)
            ->set('selfActionReason', 'Jadwal berubah')
            ->call('confirmSelfAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'cancelled']);

        $newRequest = AttendanceRequests::where('user_id', $employee->id)->where('id', '!=', $request->id)->firstOrFail();
        $this->assertSame('in_review', $newRequest->status);
        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $newRequest->id,
            'start_date' => $newStart,
            'end_date' => $newStart,
        ]);
    }

    public function test_reschedule_after_approved_reverts_roster_and_attendance_record(): void
    {
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Shift', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi',
            'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false,
        ]);
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleAssignment::create([
            'company_id' => $this->company->id, 'schedule_id' => $schedule->id,
            'user_id' => $employee->id, 'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $oldStart = now()->addDays(31)->toDateString();
        $newStart = now()->addDays(40)->toDateString();

        $roster = ScheduleShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $employee->id,
            'shift_type_id' => $pagi->id, 'date' => $oldStart, 'off_day' => false,
        ]);

        $request = $this->submitLeave($employee, $oldStart);

        Livewire::actingAs($this->approver)
            ->test(AttendanceRequestDetail::class, ['record' => $request])
            ->call('chooseAction', 'approve')
            ->set('note', 'Disetujui')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'approved']);
        $roster->refresh();
        $this->assertTrue((bool) $roster->off_day);
        $this->assertDatabaseHas('attendance_records', ['user_id' => $employee->id, 'date' => $oldStart, 'status' => 'leave']);

        $this->actingAs($employee);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request->fresh()])
            ->assertSet('canReschedule', true)
            ->call('chooseSelfAction', 'reschedule')
            ->set('rescheduleStart', $newStart)
            ->set('rescheduleEnd', $newStart)
            ->set('selfActionReason', 'Ada keperluan mendadak, geser tanggal')
            ->call('confirmSelfAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'cancelled']);

        // revertApprovedAbsenceSync() hapus baris roster/attendance_records yang bertanda
        // source_request_id ini (perilaku sama seperti pembatalan biasa via approval,
        // AttendanceCancellationApprovalTest) - baris roster Libur lama jadi tidak ada lagi.
        $this->assertDatabaseMissing('schedule_shift_assignments', ['id' => $roster->id]);
        $this->assertDatabaseMissing('attendance_records', ['source_request_id' => $request->id]);

        $newRequest = AttendanceRequests::where('user_id', $employee->id)->where('id', '!=', $request->id)->firstOrFail();
        $this->assertSame('in_review', $newRequest->status);
    }

    public function test_permission_request_cannot_be_rescheduled(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_seharian',
            'date' => now()->addDays(3)->toDateString(),
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->actingAs($employee);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('canReschedule', false);
    }
}
