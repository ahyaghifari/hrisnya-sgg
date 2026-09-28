<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kepala Ruangan (Update:ShiftScheduleAssignments TANPA Update:Schedule) tidak boleh langsung
 * menulis roster lewat saveRoster() - perubahan diajukan dulu ke approval, baru benar-benar
 * ditulis begitu disetujui (instruksi user 2026-09-28). Lihat ScheduleRosterChangeService &
 * ApprovalFlowService::advanceStep() cabang SCHEDULE_ROSTER_CHANGE.
 */
class ScheduleRosterChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** company_id WAJIB diisi (beda dari actingAsAbsensi() di ScheduleShiftRosterTabTest yang
     * tidak butuh company_id karena tidak pernah dipakai lintas company) - ScheduleRosterChangeService
     * butuh company_id pengaju buat resolveApprovalFlowForUser(). */
    private function actingAsKaru(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Karu Roster Approval Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'APR', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Manual',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);

        return [$schedule, $shiftType, $employee];
    }

    private function makeDefaultApprovalFlow(Companies $company, User $approver): MasterApprovalFlows
    {
        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'schedule_roster_change',
            'name' => 'Alur Perubahan Jadwal Shift',
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

        return $flow;
    }

    public function test_karu_without_update_schedule_creates_pending_request_instead_of_writing_directly(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster')
            ->assertSet('editMode', false);

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists(),
            'Belum boleh tertulis sebelum disetujui.'
        );

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('in_review', $request->status);
        $this->assertSame($karu->id, $request->requested_by);
        $this->assertNotNull($request->approvalSnapshot);
    }

    public function test_approving_the_request_writes_the_roster(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $approver->id,
            'Disetujui',
            'approved',
        );

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('shift_type_id', $shiftType->id)
                ->where('off_day', false)
                ->exists()
        );
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_rejecting_the_request_never_writes_the_roster(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $approver->id,
            'Ditolak',
            'rejected',
        );

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_missing_default_flow_shows_error_and_does_not_create_a_request(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $this->assertFalse(ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->exists());
        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }
}
