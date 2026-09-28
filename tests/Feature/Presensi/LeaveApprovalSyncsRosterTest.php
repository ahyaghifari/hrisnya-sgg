<?php

namespace Tests\Feature\Presensi;

use App\Enum\ShiftType;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
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
 * Cuti & Izin Tidak Masuk yang disetujui otomatis ditandai Libur di roster shift (Detail
 * Penjadwalan) - instruksi user 2026-09-14, lihat
 * AttendaceReqService::syncApprovedAbsenceToRoster().
 */
class LeaveApprovalSyncsRosterTest extends TestCase
{
    use RefreshDatabase;

    private function makeShiftEmployee(Companies $company): array
    {
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift',
            'schedule_type' => 'shift',
        ]);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'P',
            'name' => 'Pagi',
            'shift_type' => ShiftType::PAGI->value,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'user_id' => $employee->id,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        return [$schedule, $pagi, $employee];
    }

    private function approveAttendanceFlow(Companies $company, User $approver): void
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

    public function test_approved_leave_marks_roster_day_off(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        [$schedule, $pagi, $employee] = $this->makeShiftEmployee($company);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approveAttendanceFlow($company, $approver);

        $date = now()->addDays(31)->toDateString();

        // Karyawan sudah terjadwal kerja Pagi hari itu - harus TERTIMPA jadi Libur begitu
        // cuti disetujui.
        $roster = ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $pagi->id,
            'date' => $date,
            'off_day' => false,
        ]);

        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $leaveType->value,
            'start_date' => $date,
            'end_date' => $date,
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->approve($request, $approver);

        $roster->refresh();
        $this->assertTrue((bool) $roster->off_day);
        $this->assertNull($roster->shift_type_id);
        $this->assertStringContainsString('Cuti', (string) $roster->notes);
    }

    public function test_approved_izin_tidak_masuk_marks_roster_day_off_across_range(): void
    {
        $company = Companies::create(['code' => 'TST2', 'name' => 'Test Company 2', 'location' => 'Test', 'is_active' => true]);
        [$schedule, $pagi, $employee] = $this->makeShiftEmployee($company);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approveAttendanceFlow($company, $approver);

        $start = now()->addDays(5)->toDateString();
        $end = now()->addDays(6)->toDateString();

        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_seharian',
            'date' => $start,
            'end_date' => $end,
            'purpose' => 'Ada keperluan keluarga',
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->approve($request, $approver);

        foreach ([$start, $end] as $d) {
            $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $d)->first();
            $this->assertNotNull($row, "Baris Libur untuk {$d} harus dibuat.");
            $this->assertTrue((bool) $row->off_day);
        }
    }

    public function test_approved_izin_sakit_marks_roster_day_off(): void
    {
        $company = Companies::create(['code' => 'TST4', 'name' => 'Test Company 4', 'location' => 'Test', 'is_active' => true]);
        [$schedule, $pagi, $employee] = $this->makeShiftEmployee($company);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approveAttendanceFlow($company, $approver);

        $date = now()->addDays(4)->toDateString();

        $roster = ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $pagi->id,
            'date' => $date,
            'off_day' => false,
        ]);

        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_sakit',
            'date' => $date,
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->approve($request, $approver);

        $roster->refresh();
        $this->assertTrue((bool) $roster->off_day);
    }

    public function test_approved_izin_meninggalkan_jam_kerja_does_not_touch_roster(): void
    {
        $company = Companies::create(['code' => 'TST3', 'name' => 'Test Company 3', 'location' => 'Test', 'is_active' => true]);
        [$schedule, $pagi, $employee] = $this->makeShiftEmployee($company);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $this->approveAttendanceFlow($company, $approver);

        $date = now()->addDays(3)->toDateString();

        $roster = ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $pagi->id,
            'date' => $date,
            'off_day' => false,
        ]);

        // Izin Terlambat Masuk = kategori Meninggalkan Jam Kerja, BUKAN hari penuh - shift hari
        // itu tetap relevan, roster tidak boleh disentuh.
        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => 'izin_terlambat_masuk',
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->approve($request, $approver);

        $roster->refresh();
        $this->assertFalse((bool) $roster->off_day);
        $this->assertSame($pagi->id, $roster->shift_type_id);
    }
}
