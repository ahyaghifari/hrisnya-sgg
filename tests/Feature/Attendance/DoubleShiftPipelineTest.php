<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Double shift (ExtraShiftAssignment category=double_shift, hasil tukar shift yang bentrok) harus
 * diperlakukan pipeline presensi SAMA PERSIS dengan extra shift biasa: yang membedakan hanya
 * jasa di payroll, bukan pairing/penyimpanan. Pagi 07-14 + Siang 14-21 menyambung (2 scan) dan
 * Pagi + Malam terpisah (4 scan), lewat cron.
 */
class DoubleShiftPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-10';

    private User $user;

    private Companies $company;

    /** @return array{0: ScheduleShiftType, 1: ScheduleShiftType, 2: ScheduleShiftType} pagi, siang, malam */
    private function setUpUser(): array
    {
        $this->company = Companies::create(['code' => 'DS1', 'name' => 'DS Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'DS001', 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $make = fn (string $code, string $start, string $end, bool $overnight) => ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => $overnight,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);

        return [$make('PAGI', '07:00:00', '14:00:00', false), $make('SIANG', '14:00:00', '21:00:00', false), $make('MALAM', '22:00:00', '06:00:00', true)];
    }

    private function assign(ScheduleShiftType $normal, ScheduleShiftType $second, string $category): void
    {
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => $normal->id, 'date' => self::DATE, 'off_day' => false]);
        ExtraShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => $second->id, 'date' => self::DATE,
            'status' => 'approved', 'source' => $category === 'double_shift' ? 'swap_conflict' : 'assigned', 'category' => $category, 'created_by' => $this->user->id,
        ]);
    }

    private function scan(string $time, string $date = self::DATE): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => $date . ' ' . $time . ':00',
            'access_date' => $date, 'access_time' => $time . ':00',
            'first_name' => $this->user->name, 'last_name' => '', 'person_name' => $this->user->name, 'person_group' => 'default',
        ]);
    }

    public static function categories(): array
    {
        return ['extra shift' => ['extra_shift'], 'double shift' => ['double_shift']];
    }

    #[DataProvider('categories')]
    public function test_adjacent_two_scans_split_into_normal_and_extra_records(string $category): void
    {
        [$pagi, $siang] = $this->setUpUser();
        $this->assign($pagi, $siang, $category);
        $this->scan('07:00');
        $this->scan('21:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'DS001')->where('date', self::DATE)->first();
        $this->assertNotNull($normal);
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertSame('14:00:00', $normal->check_out_time);

        $extra = ExtraShiftAttendanceRecord::first();
        $this->assertNotNull($extra);
        $this->assertSame('14:00:00', $extra->check_in_time);
        $this->assertSame('21:00:00', $extra->check_out_time);
    }

    #[DataProvider('categories')]
    public function test_separate_shifts_need_four_scans_and_split_in_order(string $category): void
    {
        [$pagi, , $malam] = $this->setUpUser();
        $this->assign($pagi, $malam, $category);
        foreach (['07:00', '14:05', '21:55'] as $time) {
            $this->scan($time);
        }
        $this->scan('06:00', '2026-08-11');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'DS001')->where('date', self::DATE)->first();
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertSame('14:05:00', $normal->check_out_time);

        $extra = ExtraShiftAttendanceRecord::first();
        $this->assertSame('21:55:00', $extra->check_in_time);
        $this->assertSame('06:00:00', $extra->check_out_time, 'pulang shift malam dipinjam dari scan pertama besok');
    }

    /**
     * Kasus nyata: roster Pagi 08:00-14:00, double shift Siang 14:00-20:00, scan 07:14 dan 20:38
     * (cukup 2 scan, dikirim satu per satu seperti cron). Hasil yang benar: attendance_records
     * 07:14-14:00 dan extra_shift 14:00-20:38.
     */
    public function test_real_case_pagi_normal_with_siang_double_shift_per_tick(): void
    {
        $this->company = Companies::create(['code' => 'DS2', 'name' => 'DS2 Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'DS001', 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $make = fn (string $code, string $start, string $end) => ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => false,
            'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $this->assign($make('PAGI', '08:00:00', '14:00:00'), $make('SIANG', '14:00:00', '20:00:00'), 'double_shift');

        $this->scan('07:14');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $afterFirst = AttendanceRecords::where('nip', 'DS001')->where('date', self::DATE)->first();
        $this->assertSame('07:14:00', $afterFirst->check_in_time);
        $this->assertNull($afterFirst->check_out_time);

        $this->scan('20:38');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'DS001')->where('date', self::DATE)->first();
        $this->assertSame('07:14:00', $normal->check_in_time);
        $this->assertSame('14:00:00', $normal->check_out_time);

        $extra = ExtraShiftAttendanceRecord::first();
        $this->assertSame('14:00:00', $extra->check_in_time);
        $this->assertSame('20:38:00', $extra->check_out_time);
    }

    #[DataProvider('categories')]
    public function test_extra_assigned_but_not_worked_does_not_swallow_normal_shift(string $category): void
    {
        [$pagi, $siang] = $this->setUpUser();
        $this->assign($pagi, $siang, $category);
        $this->scan('07:02');
        $this->scan('14:03');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'DS001')->where('date', self::DATE)->first();
        $this->assertNotNull($normal);
        $this->assertSame('07:02:00', $normal->check_in_time);
        $this->assertSame('14:00:00', $normal->check_out_time);
        $this->assertSame(0, ExtraShiftAttendanceRecord::count(), 'extra tidak dikerjakan tidak boleh tercatat');
    }
}
