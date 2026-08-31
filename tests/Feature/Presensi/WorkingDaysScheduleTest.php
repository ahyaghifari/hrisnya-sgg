<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * total_days pengajuan Cuti/Izin > 1 tanggal = hari kerja saja — off_day jadwal user
 * (ScheduleFixedDay.is_off_day) tidak dihitung. Lihat ScheduleResolver::workingDaysBetween().
 */
class WorkingDaysScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->employee = User::factory()->create(['company_id' => $this->company->id]);

        $approver = User::factory()->create(['company_id' => $this->company->id]);
        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id, 'module' => 'attendance_request',
            'name' => 'Alur', 'flow_type' => 'default', 'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user',
            'approver_ref_id' => $approver->id, 'approval_mode' => 'any',
            'scope_type' => 'global', 'is_final' => true,
        ]);
    }

    private function giveFixedScheduleSatSunOff(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Senin-Jumat',
            'schedule_type' => 'fixed',
        ]);

        foreach (range(0, 6) as $dow) {
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id,
                'day_of_week' => $dow,
                'is_off_day' => in_array($dow, [0, 6], true), // Minggu & Sabtu libur
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);
        }

        ScheduleAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);
    }

    public function test_resolver_excludes_off_days(): void
    {
        $this->giveFixedScheduleSatSunOff();

        $monday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY);

        $resolver = app(ScheduleResolver::class);

        // Senin–Minggu = 7 hari kalender, 5 hari kerja.
        $this->assertSame(5, $resolver->workingDaysBetween($this->employee, $monday->toDateString(), $monday->copy()->addDays(6)->toDateString()));
        // Jumat–Senin (lewat 1 weekend) = 4 hari kalender, 2 hari kerja.
        $this->assertSame(2, $resolver->workingDaysBetween($this->employee, $monday->copy()->addDays(4)->toDateString(), $monday->copy()->addDays(7)->toDateString()));
    }

    public function test_shift_roster_off_day_excluded_but_unrostered_day_counted(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id, 'name' => 'Shift', 'schedule_type' => 'shift',
        ]);
        $shiftType = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi',
            'start_time' => '07:00', 'end_time' => '15:00',
        ]);

        $d1 = Carbon::parse('2026-09-07'); // hari 1
        // hari 1: masuk. hari 2: TIDAK diroster (n/a). hari 3: off_day. hari 4: masuk.
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->employee->id, 'shift_type_id' => $shiftType->id, 'off_day' => false, 'date' => $d1->toDateString()]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->employee->id, 'shift_type_id' => null, 'off_day' => true, 'date' => $d1->copy()->addDays(2)->toDateString()]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->employee->id, 'shift_type_id' => $shiftType->id, 'off_day' => false, 'date' => $d1->copy()->addDays(3)->toDateString()]);

        // Hari 1–4: hari 3 off (-1), hari 2 n/a tetap dihitung => 3 hari kerja.
        $this->assertSame(3, app(ScheduleResolver::class)->workingDaysBetween($this->employee, $d1->toDateString(), $d1->copy()->addDays(3)->toDateString()));
    }

    public function test_no_schedule_falls_back_to_calendar_days(): void
    {
        $monday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY);

        $this->assertSame(7, app(ScheduleResolver::class)->workingDaysBetween($this->employee, $monday->toDateString(), $monday->copy()->addDays(6)->toDateString()));
    }

    public function test_submitted_leave_stores_working_days_as_total(): void
    {
        $this->giveFixedScheduleSatSunOff();

        $type = LeaveTypes::create([
            'company_id' => $this->company->id, 'name' => 'Cuti Tahunan',
            'category' => LeaveTypeCategoryEnum::TAHUNAN->value, 'quota_days' => 12,
            'reset_period' => 'yearly', 'is_paid' => true,
        ]);

        $monday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $type->id,
            'is_one_day' => false,
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addDays(6)->toDateString(),
        ], $this->employee);

        $request = AttendanceRequests::where('user_id', $this->employee->id)->firstOrFail();

        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $request->id,
            'total_days' => 5,
        ]);
    }

    public function test_single_day_leave_is_one(): void
    {
        $this->giveFixedScheduleSatSunOff();

        $type = LeaveTypes::create([
            'company_id' => $this->company->id, 'name' => 'Cuti Tahunan',
            'category' => LeaveTypeCategoryEnum::TAHUNAN->value, 'quota_days' => 12,
            'reset_period' => 'yearly', 'is_paid' => true,
        ]);

        // Sabtu (off day) — 1 tanggal tetap total_days 1 (yang diajukan dihormati).
        $saturday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY)->addDays(5);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $type->id,
            'is_one_day' => true,
            'start_date' => $saturday->toDateString(),
            'end_date' => $saturday->toDateString(),
        ], $this->employee);

        $request = AttendanceRequests::where('user_id', $this->employee->id)->firstOrFail();

        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $request->id,
            'total_days' => 1,
        ]);
    }
}
