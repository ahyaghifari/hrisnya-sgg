<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Approver boleh merevisi tanggal saat approve — HANYA Cuti Khusus & Izin Khusus
 * (AttendanceRequestDetail::dateRevisable + AttendaceReqService::reviseRequestDates).
 */
class ApproverReviseDatesTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

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
            'approver_ref_id' => $this->approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    private function femaleEmployee(): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'gender' => 'perempuan']);

        return $user;
    }

    private function submitLeave(User $employee, LeaveTypeCategoryEnum $cat, int $quota, string $start, ?string $end = null): AttendanceRequests
    {
        $type = LeaveTypes::create([
            'company_id' => $this->company->id,
            'name' => $cat->label(),
            'category' => $cat->value,
            'quota_days' => $quota,
            'reset_period' => 'none',
            'is_paid' => true,
        ]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $type->id,
            'is_one_day' => $end === null,
            'start_date' => $start,
            'end_date' => $end ?? $start,
        ], $employee);

        return AttendanceRequests::where('user_id', $employee->id)->firstOrFail();
    }

    public function test_approver_revises_special_leave_dates_on_approve(): void
    {
        $employee = $this->femaleEmployee();
        $start = now()->addMonthNoOverflow()->startOfMonth()->addDays(5);
        $request = $this->submitLeave($employee, LeaveTypeCategoryEnum::MELAHIRKAN, 90, $start->toDateString());

        $newStart = $start->copy()->addDays(2);
        $newEnd = $start->copy()->addDays(11); // 10 hari

        $this->actingAs($this->approver);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('dateRevisable', true)
            ->call('chooseAction', 'approve')
            ->assertSet('reviseStart', $start->toDateString())
            ->set('reviseStart', $newStart->toDateString())
            ->set('reviseEnd', $newEnd->toDateString())
            ->set('note', 'Disetujui dengan penyesuaian tanggal')
            ->call('confirmAction')
            ->assertSet('canApprove', false)        // kartu "Aksi Persetujuan" ikut hilang
            ->assertSet('dateRevisable', false);

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'approved']);
        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $request->id,
            'start_date' => $newStart->toDateString(),
            'end_date' => $newEnd->toDateString(),
            'total_days' => 10,
        ]);
    }

    public function test_revise_exceeding_quota_blocks_approval(): void
    {
        $employee = $this->femaleEmployee();
        $start = now()->addMonthNoOverflow()->startOfMonth();
        $request = $this->submitLeave($employee, LeaveTypeCategoryEnum::MELAHIRKAN, 90, $start->toDateString());

        $this->actingAs($this->approver);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->call('chooseAction', 'approve')
            ->set('reviseStart', $start->toDateString())
            ->set('reviseEnd', $start->copy()->addDays(99)->toDateString()) // 100 > 90
            ->set('note', 'Coba lewat kuota')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'in_review']);
        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $request->id,
            'start_date' => $start->toDateString(),
            'total_days' => 1,
        ]);
    }

    public function test_regular_leave_is_not_date_revisable(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $request = $this->submitLeave($employee, LeaveTypeCategoryEnum::TAHUNAN, 12, now()->addDay()->toDateString());

        $this->actingAs($this->approver);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('dateRevisable', false)
            ->call('chooseAction', 'approve')
            ->assertSet('reviseStart', null);
    }

    public function test_special_permission_dates_revisable_and_rule_days_shown(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);

        $permType = PermissionTypes::create([
            'company_id' => $this->company->id,
            'name' => 'Menikah',
            'category' => PermissionTypeCategoryEnum::IZIN_KHUSUS->value,
            'default_days' => 3,
            'description' => 'Pernikahan karyawan sendiri, sesuai PKB pasal 12.',
        ]);

        $start = now()->addWeek();
        app(AttendaceReqService::class)->storePermissionRequest([
            'type_id' => $permType->id,
            'date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'start_time' => null,
            'end_time' => null,
            'purpose' => null,
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $newEnd = $start->copy()->addDays(4); // 5 hari

        $this->actingAs($this->approver);
        Livewire::test(AttendanceRequestDetail::class, ['record' => $request])
            ->assertSet('dateRevisable', true)
            ->assertSee('Ketentuan Aturan')
            ->assertSee('3 hari')
            ->assertSee('sesuai PKB pasal 12')
            ->call('chooseAction', 'approve')
            ->set('reviseStart', $start->toDateString())
            ->set('reviseEnd', $newEnd->toDateString())
            ->set('note', 'Disetujui')
            ->call('confirmAction');

        $this->assertDatabaseHas('attendance_requests', ['id' => $request->id, 'status' => 'approved']);
        $this->assertDatabaseHas('attendance_req_permission_details', [
            'request_id' => $request->id,
            'date' => $start->toDateString(),
            'end_date' => $newEnd->toDateString(),
            'total_days' => 5,
        ]);
    }
}
