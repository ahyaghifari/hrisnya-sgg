<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\AttendanceStatus;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cuti & Izin Tidak Masuk/Sakit/Khusus yang disetujui otomatis dicatat di attendance_records -
 * instruksi user 2026-09-24, lihat AttendaceReqService::syncApprovedAbsenceToAttendanceRecords().
 */
class LeaveApprovalSyncsAttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    private function approvalFlow(Companies $company, User $approver): void
    {
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
    }

    private function approve(AttendanceRequests $request, User $approver): void
    {
        Livewire::actingAs($approver)
            ->test(AttendanceRequestDetail::class, ['record' => $request])
            ->call('chooseAction', 'approve')
            ->set('note', 'Disetujui lewat test otomatis')
            ->call('confirmAction');
    }

    public function test_approved_leave_creates_attendance_record_with_leave_status(): void
    {
        $company = Companies::create(['code' => 'ATR1', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approvalFlow($company, $approver);

        $date = now()->addDays(31)->toDateString();

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => $date,
            'end_date' => $date,
        ], $employee);

        $this->approve(AttendanceRequests::where('user_id', $employee->id)->firstOrFail(), $approver);

        $record = AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::LEAVE->value, $record->status);
    }

    public function test_approved_izin_sakit_creates_attendance_record_with_sick_status(): void
    {
        $company = Companies::create(['code' => 'ATR2', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approvalFlow($company, $approver);

        $date = now()->addDays(4)->toDateString();

        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_sakit',
            'date' => $date,
        ], $employee);

        $this->approve(AttendanceRequests::where('user_id', $employee->id)->firstOrFail(), $approver);

        $record = AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::SICK->value, $record->status);
    }

    public function test_approved_izin_khusus_creates_attendance_record_with_permit_status(): void
    {
        $company = Companies::create(['code' => 'ATR3', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approvalFlow($company, $approver);

        $date = now()->addDays(5)->toDateString();

        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_khusus',
            'date' => $date,
        ], $employee);

        $this->approve(AttendanceRequests::where('user_id', $employee->id)->firstOrFail(), $approver);

        $record = AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::PERMIT->value, $record->status);
    }

    public function test_approved_izin_terlambat_masuk_does_not_create_attendance_record(): void
    {
        $company = Companies::create(['code' => 'ATR4', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approvalFlow($company, $approver);

        $date = now()->addDays(3)->toDateString();

        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_terlambat_masuk',
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ], $employee);

        $this->approve(AttendanceRequests::where('user_id', $employee->id)->firstOrFail(), $approver);

        $this->assertNull(AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first());
    }

    public function test_admin_created_leave_without_approval_still_syncs_attendance_record(): void
    {
        $company = Companies::create(['code' => 'ATR6', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);

        $date = now()->addDays(3)->toDateString();

        // Dibuat admin lewat Filament dengan toggle "need_approval" dimatikan - langsung
        // approved, tidak lewat ApprovalFlowService::advanceStep() sama sekali.
        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => $date,
            'end_date' => $date,
        ], $employee, skipApproval: true, createdBy: $admin);

        $record = AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::LEAVE->value, $record->status);
    }

    public function test_approved_leave_does_not_overwrite_existing_check_in(): void
    {
        $company = Companies::create(['code' => 'ATR5', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approvalFlow($company, $approver);

        $date = now()->addDays(31)->toDateString();

        AttendanceRecords::create([
            'user_id' => $employee->id,
            'nip' => $employee->nip,
            'date' => $date,
            'check_in_time' => '08:00:00',
            'status' => AttendanceStatus::PRESENT->value,
            'source' => 'device',
        ]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => $date,
            'end_date' => $date,
        ], $employee);

        $this->approve(AttendanceRequests::where('user_id', $employee->id)->firstOrFail(), $approver);

        $record = AttendanceRecords::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }
}
