<?php

namespace Tests\Feature\Attendance;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendanceCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembatalan cuti/izin yang SUDAH approved - lewat modul approval terpisah
 * (ATTENDANCE_REQUEST_CANCELLATION). Approve -> pengajuan asli jadi cancelled + efek sync
 * (roster/attendance_records) direvert. Reject -> pengajuan asli tetap approved.
 */
class AttendanceCancellationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $approver;
    private User $cancellationApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);
        $this->cancellationApprover = User::factory()->create(['company_id' => $this->company->id]);

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

        $cancellationFlow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'attendance_request_cancellation',
            'name' => 'Alur Pembatalan Cuti',
            'flow_type' => 'default',
            'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $cancellationFlow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->cancellationApprover->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    /** $createdBy null = self-service (kena H-30 Cuti Tahunan); diisi eksplisit (mis. admin) buat
     *  simulasi data lama/lewat H yang butuh tanggal di bawah H-30 - lihat
     *  test_cannot_request_cancellation_after_cutoff(). */
    private function approvedLeave(User $employee, string $start, ?User $createdBy = null): AttendanceRequests
    {
        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'is_one_day' => true,
            'start_date' => $start,
            'end_date' => $start,
        ], $employee, createdBy: $createdBy ?? $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();
        $request->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $this->approver->id,
            'Disetujui',
            'approved'
        );

        return $request->fresh();
    }

    public function test_approving_cancellation_reverts_status_and_sync(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $start = now()->addDays(31)->toDateString();
        $request = $this->approvedLeave($employee, $start);

        // Sync approval sudah menandai attendance_records - pastikan itu memang terjadi dulu
        // sebelum kita tes revert-nya.
        $this->assertDatabaseHas('attendance_records', [
            'nip' => $employee->nip,
            'date' => $start,
            'status' => 'leave',
            'source_request_id' => $request->id,
        ]);

        $cancellation = app(AttendanceCancellationService::class)->requestCancellation(
            $request,
            $employee,
            'Ternyata batal, ada kebutuhan mendadak'
        );

        $this->actingAs($this->cancellationApprover);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('canApproveCancellation', true)
            ->call('chooseCancellationAction', 'approve')
            ->set('cancellationNote', 'Disetujui, silakan batal')
            ->call('confirmCancellationAction')
            ->assertSet('canApproveCancellation', false);

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('attendance_request_cancellations', ['id' => $cancellation->id, 'status' => 'approved']);
        $this->assertDatabaseMissing('attendance_records', ['source_request_id' => $request->id]);
    }

    public function test_rejecting_cancellation_keeps_original_request_approved(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $start = now()->addDays(31)->toDateString();
        $request = $this->approvedLeave($employee, $start);

        $cancellation = app(AttendanceCancellationService::class)->requestCancellation(
            $request,
            $employee,
            'Coba batal'
        );

        $this->actingAs($this->cancellationApprover);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->call('chooseCancellationAction', 'reject')
            ->set('cancellationNote', 'Tidak bisa, sudah terlanjur dijadwalkan')
            ->call('confirmCancellationAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'approved']);
        $this->assertDatabaseHas('attendance_request_cancellations', ['id' => $cancellation->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('attendance_records', ['source_request_id' => $request->id, 'status' => 'leave']);
    }

    public function test_without_cancellation_flow_falls_back_to_first_approver_of_original_request(): void
    {
        MasterApprovalFlows::where('module', 'attendance_request_cancellation')->delete();

        // Alur pengajuan asli dibuat 2 step (approver, lalu manager) - pembatalan cukup step pertama.
        $manager = User::factory()->create(['company_id' => $this->company->id]);
        $leaveFlow = MasterApprovalFlows::where('module', 'attendance_request')->firstOrFail();
        $leaveFlow->steps()->update(['is_final' => false]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $leaveFlow->id,
            'step_order' => 2,
            'approver_type' => 'user',
            'approver_ref_id' => $manager->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $start = now()->addDays(31)->toDateString();

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'is_one_day' => true,
            'start_date' => $start,
            'end_date' => $start,
        ], $employee, createdBy: $employee);
        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();
        $flowService = app(ApprovalFlowService::class);
        $flowService->advanceStep($request->approvalSnapshot, $this->approver->id, 'OK', 'approved');
        $flowService->advanceStep($request->approvalSnapshot->fresh(), $manager->id, 'OK', 'approved');
        $request = $request->fresh();
        $this->assertSame('approved', $request->status);

        $cancellation = app(AttendanceCancellationService::class)->requestCancellation($request, $employee, 'Coba batal');

        $snapshot = \App\Models\ApprovalManagement\ApprovalSnapshots::where('module_type', 'attendance_request_cancellation')
            ->where('module_id', $cancellation->id)->firstOrFail();
        $this->assertCount(1, $snapshot->steps_snapshot);
        $this->assertSame($this->approver->id, $snapshot->steps_snapshot[0]['approver_ref_id']);

        // Approver pertama saja sudah cukup menyetujui pembatalan.
        $flowService->advanceStep($snapshot, $this->approver->id, 'Silakan batal', 'approved');
        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'cancelled']);
    }

    /** Admin panel: tombol Batalkan -> logika sama dengan portal (cancelRequestDirectly), atas nama admin. */
    public function test_admin_can_cancel_approved_leave_and_sync_is_reverted(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id]);
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $start = now()->addDays(31)->toDateString();
        $request = $this->approvedLeave($employee, $start);
        $this->assertDatabaseHas('attendance_records', ['source_request_id' => $request->id]);

        \Illuminate\Support\Facades\Notification::fake();

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\AttendanceRequests\Pages\EditAttendanceRequest::class, ['record' => $request->id])
            ->assertActionVisible('cancelRequest')
            ->callAction('cancelRequest', ['reason' => 'Salah input']);

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertSame($admin->id, $request->cancelled_by);
        $this->assertDatabaseMissing('attendance_records', ['source_request_id' => $request->id]);
        $this->assertDatabaseHas('approval_histories', ['requestable_id' => $request->id, 'action' => 'cancelled', 'actor_id' => $admin->id]);
        \Illuminate\Support\Facades\Notification::assertSentTo($employee, \App\Notifications\RequestStatusChanged::class);
    }

    public function test_admin_cannot_cancel_after_cutoff(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id]);
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->approvedLeave($employee, now()->subDay()->toDateString(), createdBy: $this->approver);

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\AttendanceRequests\Pages\EditAttendanceRequest::class, ['record' => $request->id])
            ->assertActionHidden('cancelRequest');
    }

    public function test_cannot_request_cancellation_after_cutoff(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->approvedLeave($employee, now()->subDay()->toDateString(), createdBy: $this->approver);

        $this->expectException(\RuntimeException::class);

        app(AttendanceCancellationService::class)->requestCancellation($request, $employee, 'Coba batal telat');
    }
}
