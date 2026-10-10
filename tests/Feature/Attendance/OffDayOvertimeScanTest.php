<?php

namespace Tests\Feature\Attendance;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\OvertimeAttendanceRecord;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Hari LIBUR jadwal Fixed/Flexible (Senin 2026-08-10) yang karyawannya tetap scan:
 * - ada pengajuan lembur aktif (in_review/approved) -> bukti lembur (overtime_attendance_records),
 *   TIDAK dihitung masuk/telat di attendance_records;
 * - tanpa pengajuan, atau pengajuannya ditolak/dibatalkan -> dibuang (bucket 'off'), tidak disimpan.
 * Dites lewat reader (keranjang) dan cron (yang menyimpan).
 */
class OffDayOvertimeScanTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-10';

    private User $user;

    private function setUpUser(string $type): void
    {
        $company = Companies::create(['code' => 'OT1', 'name' => 'OT Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'OT001', 'company_id' => $company->id]);

        if ($type === 'fixed') {
            $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => true, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'break_minutes' => 60, 'min_work_minutes' => 0, 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
                'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
            ]);
        } else {
            $schedule = Schedule::create([
                'company_id' => $company->id, 'name' => 'Fleksibel', 'schedule_type' => 'flexible',
                'flexible_checkin_from' => '06:00:00', 'flexible_checkin_until' => '10:00:00',
            ]);
            ScheduleFlexibleDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => true, 'break_minutes' => 60,
                'min_work_minutes' => 420, 'max_work_minutes' => 480, 'counted_work_days' => 1, 'late_checkin_deadline' => '09:00:00',
            ]);
        }

        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $this->user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);
    }

    private function scan(string $time): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => self::DATE . ' ' . $time . ':00',
            'access_date' => self::DATE, 'access_time' => $time . ':00',
            'first_name' => $this->user->name, 'last_name' => '', 'person_name' => $this->user->name, 'person_group' => 'default',
        ]);
    }

    private function overtimeRequest(AttendanceReqStatus $status): void
    {
        $request = AttendanceRequests::create(['user_id' => $this->user->id, 'type' => 'overtime', 'status' => $status->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $request->id, 'date' => self::DATE, 'total_hours' => 3.0]);
    }

    public static function types(): array
    {
        return ['fixed' => ['fixed'], 'flexible' => ['flexible']];
    }

    #[DataProvider('types')]
    public function test_off_day_scan_without_overtime_request_is_dropped(string $type): void
    {
        $this->setUpUser($type);
        $this->scan('08:00');
        $this->scan('16:00');

        $b = app(AccessLogScheduleReader::class)->readAndGroup(self::DATE, self::DATE)['buckets'];
        $this->assertCount(1, $b['off']);
        $this->assertSame([], $b[$type]);
        $this->assertSame([], $b['overtime']);

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        $this->assertSame(0, AttendanceRecords::count());
        $this->assertSame(0, OvertimeAttendanceRecord::count());
    }

    #[DataProvider('types')]
    public function test_off_day_scan_with_active_overtime_request_becomes_overtime_proof(string $type): void
    {
        $this->setUpUser($type);
        $this->overtimeRequest(AttendanceReqStatus::IN_REVIEW);
        $this->scan('08:00');
        $this->scan('16:00');

        $b = app(AccessLogScheduleReader::class)->readAndGroup(self::DATE, self::DATE)['buckets'];
        $this->assertCount(1, $b['overtime']);
        $this->assertSame([], $b['off']);
        $this->assertSame([], $b[$type], 'lembur hari libur tidak boleh jadi masuk/telat');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertSame(0, AttendanceRecords::count(), 'tidak ada attendance_records untuk hari libur');
        $record = OvertimeAttendanceRecord::where('user_id', $this->user->id)->where('date', self::DATE)->first();
        $this->assertNotNull($record);
        $this->assertSame('08:00:00', $record->check_in_time);
        $this->assertSame('16:00:00', $record->check_out_time);
        $this->assertSame(480, $record->working_minutes);
    }

    #[DataProvider('types')]
    public function test_approved_overtime_request_also_counts(string $type): void
    {
        $this->setUpUser($type);
        $this->overtimeRequest(AttendanceReqStatus::APPROVED);
        $this->scan('09:00');
        $this->scan('12:30');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = OvertimeAttendanceRecord::where('user_id', $this->user->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(210, $record->working_minutes);
    }

    #[DataProvider('types')]
    public function test_rejected_or_cancelled_overtime_request_does_not_count(string $type): void
    {
        $this->setUpUser($type);
        $this->overtimeRequest(AttendanceReqStatus::REJECTED);
        $this->overtimeRequest(AttendanceReqStatus::CANCELLED);
        $this->scan('08:00');
        $this->scan('16:00');

        $b = app(AccessLogScheduleReader::class)->readAndGroup(self::DATE, self::DATE)['buckets'];
        $this->assertSame([], $b['overtime']);
        $this->assertCount(1, $b['off']);
    }

    #[DataProvider('types')]
    public function test_single_scan_with_overtime_request_keeps_check_in_only(string $type): void
    {
        $this->setUpUser($type);
        $this->overtimeRequest(AttendanceReqStatus::APPROVED);
        $this->scan('08:10');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = OvertimeAttendanceRecord::where('user_id', $this->user->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('08:10:00', $record->check_in_time);
        $this->assertNull($record->check_out_time);
        $this->assertNull($record->working_minutes);
    }

    /** Scan sudah terlanjur dihitung masuk/telat sebelum pengajuan lembur dibuat: barisnya hilang
     * saat bukti lembur tersimpan, yang dikoreksi manual HR dibiarkan. */
    #[DataProvider('types')]
    public function test_auto_attendance_row_is_replaced_but_manual_correction_is_kept(string $type): void
    {
        $this->setUpUser($type);
        AttendanceRecords::create([
            'user_id' => $this->user->id, 'nip' => $this->user->nip, 'date' => self::DATE,
            'check_in_time' => '08:00:00', 'status' => 'present', 'source' => 'access_logs_' . $type, 'is_auto_calculated' => true,
        ]);

        $this->overtimeRequest(AttendanceReqStatus::APPROVED);
        $this->scan('08:00');
        $this->scan('16:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertSame(0, AttendanceRecords::where('date', self::DATE)->count(), 'baris otomatis dihapus');

        AttendanceRecords::create([
            'user_id' => $this->user->id, 'nip' => $this->user->nip, 'date' => self::DATE,
            'check_in_time' => '07:30:00', 'status' => 'present', 'source' => 'manual', 'is_auto_calculated' => false,
        ]);
        $this->scan('17:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertSame(1, AttendanceRecords::where('date', self::DATE)->where('is_auto_calculated', false)->count(), 'koreksi manual HR tidak dihapus');
    }
}
