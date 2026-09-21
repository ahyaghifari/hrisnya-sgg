<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi buat pairScansForEmployee()/readAndGroupScansIncremental() (AccessLogScheduleReader) -
 * refactor buat overnight shift (lihat OvernightShiftPairingTest) menggeser resolveUsers ke lebih
 * awal & mengganti grouping per-access_date jadi per-tanggal-shift. Fixed/Flexible TIDAK punya
 * konsep overnight, jadi behaviour-nya untuk kasus normal (same-day check-in/check-out) harus
 * identik dengan sebelum refactor.
 */
class FixedFlexibleReaderTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function insertScan(User $user, string $datetime): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $user->nip,
            'access_datetime' => $datetime,
            'access_date' => substr($datetime, 0, 10),
            'access_time' => substr($datetime, 11),
            'first_name' => $user->name,
            'last_name' => '',
            'person_name' => $user->name,
            'person_group' => 'default',
        ]);
    }

    private function makeFixedUser(string $date): User
    {
        $user = User::factory()->create(['nip' => 'FIX001', 'company_id' => $this->company->id]);

        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Jadwal Kantor',
            'schedule_type' => 'fixed',
        ]);

        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'is_off_day' => false,
            'start_time' => '08:00:00',
            'break_minutes' => 60,
            'min_work_minutes' => 0,
            'end_time' => '16:00:00',
            'checkin_buffer_minutes' => 60,
            'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 0,
            'counted_work_days' => 1,
        ]);

        ScheduleAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $user;
    }

    private function makeFlexibleUser(): User
    {
        $user = User::factory()->create(['nip' => 'FLX001', 'company_id' => $this->company->id]);

        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Jadwal Fleksibel',
            'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00',
            'flexible_checkin_until' => '10:00:00',
        ]);

        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => Carbon::parse('2026-08-06')->dayOfWeek,
            'is_off_day' => false,
            'break_minutes' => 60,
            'min_work_minutes' => 420,
            'max_work_minutes' => 480,
            'counted_work_days' => 1,
            'late_checkin_deadline' => '09:00:00',
        ]);

        ScheduleAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $user;
    }

    public function test_fixed_batch_read_pairs_same_day_checkin_checkout_and_flags_late(): void
    {
        $user = $this->makeFixedUser('2026-08-06');
        $this->insertScan($user, '2026-08-06 08:20:00'); // 20 menit lewat toleransi 15
        $this->insertScan($user, '2026-08-06 16:00:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06');

        $this->assertCount(1, $result['buckets']['fixed']);
        $this->assertCount(0, $result['unresolved_schedule']);

        $r = app(FixedScheduleCalculator::class)->calculate($result['buckets']['fixed'])[0];
        $this->assertSame(5, $r['late_minutes']);
        $this->assertSame('late', $r['status']);
        $this->assertSame(400, $r['working_minutes']); // 08:20-16:00 (7j40m) - 1j istirahat
    }

    public function test_fixed_incremental_merges_checkin_then_checkout_same_day(): void
    {
        $user = $this->makeFixedUser('2026-08-06');
        $reader = app(AccessLogScheduleReader::class);

        $this->insertScan($user, '2026-08-06 08:00:00');
        $first = $reader->readAndGroupIncremental(null);
        $this->assertCount(1, $first['buckets']['fixed']);
        $this->assertSame('08:00:00', $first['buckets']['fixed'][0]['check_in']);
        $this->assertNull($first['buckets']['fixed'][0]['check_out']);

        app(AttendanceRecordRepository::class)->saveCalculatedResult(
            app(FixedScheduleCalculator::class)->calculate($first['buckets']['fixed'])[0],
            'access_logs_fixed'
        );

        $this->insertScan($user, '2026-08-06 16:00:00');
        $second = $reader->readAndGroupIncremental($first['max_synced_at']);

        $this->assertCount(1, $second['buckets']['fixed']);
        $entry = $second['buckets']['fixed'][0];
        $this->assertSame('2026-08-06', $entry['date']);
        $this->assertSame('08:00:00', $entry['check_in']);
        $this->assertSame('16:00:00', $entry['check_out']);
        $this->assertTrue($entry['is_new_checkout']);
    }

    /** Guard "shift yang sudah lengkap tidak tertimpa scan tambahan" (isCompletedShiftDay) HANYA untuk
     * jadwal Shift - Fixed/Flexible yang jam masuk & pulangnya sudah lengkap tetap harus menerima
     * scan berikutnya lewat sync incremental (jam pulang ikut diperbarui). */
    public function test_fixed_and_flexible_with_complete_record_still_accept_extra_scan_incrementally(): void
    {
        foreach (['fixed' => $this->makeFixedUser('2026-08-06'), 'flexible' => $this->makeFlexibleUser()] as $type => $user) {
            $reader = app(AccessLogScheduleReader::class);
            $calculator = $type === 'fixed' ? app(FixedScheduleCalculator::class) : app(FlexibleScheduleCalculator::class);

            $this->insertScan($user, '2026-08-06 08:00:00');
            $this->insertScan($user, '2026-08-06 16:00:00');
            $first = $reader->readAndGroupIncremental(null);
            $this->assertCount(1, $first['buckets'][$type], $type);
            app(AttendanceRecordRepository::class)->saveCalculatedResult(
                $calculator->calculate($first['buckets'][$type])[0],
                'access_logs_'.$type
            );

            // Scan ke-3 (mis. pulang lebih akhir) - harus MASUK sebagai entri baru, bukan dilewati.
            $this->insertScan($user, '2026-08-06 17:30:00');
            $second = $reader->readAndGroupIncremental($first['max_synced_at']);

            $this->assertCount(1, $second['buckets'][$type], "{$type}: scan tambahan setelah record lengkap tidak boleh dilewati");
            $this->assertSame('17:30:00', $second['buckets'][$type][0]['check_out'], $type);

            DB::table('access_logs')->delete();
            DB::table('attendance_records')->delete();
        }
    }

    public function test_fixed_lone_scan_next_day_is_not_wrongly_merged_as_previous_day_checkout(): void
    {
        // Fixed TIDAK overnight - scan tunggal di hari berikutnya harus jadi entri hari itu
        // sendiri (kemungkinan besar telat/out_of_window), BUKAN "dipinjam" jadi checkout hari
        // sebelumnya (itu cuma berlaku utk shift is_overnight).
        $user = $this->makeFixedUser('2026-08-06');
        $this->makeFixedDayFor($user, '2026-08-07');

        $this->insertScan($user, '2026-08-06 08:00:00'); // check-in doang, belum checkout
        $this->insertScan($user, '2026-08-07 08:05:00'); // check-in hari berikutnya

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-07');

        $this->assertCount(2, $result['buckets']['fixed']);
        $byDate = collect($result['buckets']['fixed'])->keyBy('date');
        $this->assertNull($byDate['2026-08-06']['check_out']);
        $this->assertSame('08:05:00', $byDate['2026-08-07']['check_in']);
    }

    private function makeFixedDayFor(User $user, string $date): void
    {
        $schedule = ScheduleAssignment::where('user_id', $user->id)->first()->schedule;
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'is_off_day' => false,
            'start_time' => '08:00:00',
            'break_minutes' => 60,
            'min_work_minutes' => 0,
            'end_time' => '16:00:00',
            'checkin_buffer_minutes' => 60,
            'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 0,
            'counted_work_days' => 1,
        ]);
    }

    public function test_flexible_batch_read_pairs_and_flags_late_checkin(): void
    {
        $user = $this->makeFlexibleUser();
        $this->insertScan($user, '2026-08-06 09:15:00'); // lewat late_checkin_deadline 09:00
        $this->insertScan($user, '2026-08-06 17:30:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06');

        $this->assertCount(1, $result['buckets']['flexible']);
        $r = app(FlexibleScheduleCalculator::class)->calculate($result['buckets']['flexible'])[0];
        $this->assertSame(15, $r['late_minutes']);
        $this->assertSame('late', $r['status']);
        $this->assertTrue($r['meets_target']);
    }
}
