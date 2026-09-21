<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi (production, 2026-09-21): karyawan yang punya scan masuk pagi tapi scan itu TIDAK ikut
 * batch incremental (masuk ke access_logs sesudah watermark lewat / jadwal belum siap saat itu) lalu
 * scan pulangnya 2x berdekatan -> baris presensi terbentuk dari 2 scan pulang itu saja: masuk =
 * pulang (mis. 18:02 dan 18:02) dan dianggap terlambat berjam-jam. Jam masuk/pulang harus dihitung
 * dari SEMUA scan hari itu (kecuali hari extra shift / shift overnight).
 */
class IncrementalMissedEarlierScanTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function scan(User $u, string $dt): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $u->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10), 'access_time' => substr($dt, 11),
            'first_name' => $u->name, 'last_name' => '', 'person_name' => $u->name, 'person_group' => 'default',
        ]);
    }

    private function shiftUser(string $code, string $start, string $end, bool $overnight, array $dates): User
    {
        $user = User::factory()->create(['nip' => 'SH'.$code, 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift '.$code, 'schedule_type' => 'shift']);
        $type = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => $code, 'shift_type' => $overnight ? 'malam' : 'pagi',
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => $overnight, 'break_minutes' => 0,
            'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        foreach ($dates as $date) {
            ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => $date, 'off_day' => false]);
        }

        return $user;
    }

    /** Scan pagi 08:00 sudah "lewat" watermark (tidak ikut batch), batch cuma bawa 2 scan pulang. */
    public function test_shift_uses_missed_morning_scan_when_only_two_close_checkout_scans_are_new(): void
    {
        $user = $this->shiftUser('P', '08:00:00', '14:00:00', false, ['2026-08-06']);
        $this->scan($user, '2026-08-06 08:00:10');
        $this->scan($user, '2026-08-06 14:05:20');
        $this->scan($user, '2026-08-06 14:07:30');

        $result = app(AccessLogScheduleReader::class)->readAndGroupIncremental('2026-08-06 09:00:00');

        $this->assertCount(1, $result['buckets']['shift']);
        $entry = $result['buckets']['shift'][0];
        $this->assertSame('08:00:10', $entry['check_in']);
        $this->assertSame('14:07:30', $entry['check_out']);
        $this->assertSame(0, (new ShiftScheduleCalculator)->calculate([$entry])[0]['late_minutes']);
    }

    public function test_fixed_and_flexible_use_missed_morning_scan_too(): void
    {
        $fixed = User::factory()->create(['nip' => 'FIX001', 'company_id' => $this->company->id]);
        $fixedSchedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $fixedSchedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'start_time' => '08:00:00', 'break_minutes' => 60,
            'min_work_minutes' => 0, 'end_time' => '16:00:00', 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $fixed->id, 'schedule_id' => $fixedSchedule->id, 'effective_from' => '2020-01-01']);

        $flex = User::factory()->create(['nip' => 'FLX001', 'company_id' => $this->company->id]);
        $flexSchedule = Schedule::create([
            'company_id' => $this->company->id, 'name' => 'Fleksibel', 'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00', 'flexible_checkin_until' => '10:00:00',
        ]);
        ScheduleFlexibleDay::create([
            'schedule_id' => $flexSchedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'break_minutes' => 60,
            'min_work_minutes' => 420, 'max_work_minutes' => 480, 'counted_work_days' => 1, 'late_checkin_deadline' => '09:00:00',
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $flex->id, 'schedule_id' => $flexSchedule->id, 'effective_from' => '2020-01-01']);

        foreach ([$fixed, $flex] as $u) {
            $this->scan($u, '2026-08-06 08:00:10');
            $this->scan($u, '2026-08-06 16:05:20');
            $this->scan($u, '2026-08-06 16:07:30');
        }

        $result = app(AccessLogScheduleReader::class)->readAndGroupIncremental('2026-08-06 09:00:00');

        foreach (['fixed', 'flexible'] as $type) {
            $this->assertCount(1, $result['buckets'][$type], $type);
            $this->assertSame('08:00:10', $result['buckets'][$type][0]['check_in'], $type);
            $this->assertSame('16:07:30', $result['buckets'][$type][0]['check_out'], $type);
        }
        $this->assertSame('present', app(FixedScheduleCalculator::class)->calculate($result['buckets']['fixed'])[0]['status']);
        $this->assertNotSame('late', app(FlexibleScheduleCalculator::class)->calculate($result['buckets']['flexible'])[0]['status']);
    }

    /** Overnight: scan dini hari di tanggal itu = checkout shift semalam, BUKAN jam masuk shift hari ini. */
    public function test_overnight_early_morning_scan_is_not_used_as_checkin_of_the_next_night_shift(): void
    {
        $user = $this->shiftUser('M', '23:00:00', '08:00:00', true, ['2026-08-06', '2026-08-07']);
        $this->scan($user, '2026-08-07 07:50:00'); // checkout shift 06 (sudah diproses batch sebelumnya)
        $this->scan($user, '2026-08-07 23:10:00'); // check-in shift 07

        $result = app(AccessLogScheduleReader::class)->readAndGroupIncremental('2026-08-07 08:00:00');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertSame('2026-08-07', $result['buckets']['shift'][0]['date']);
        $this->assertSame('23:10:00', $result['buckets']['shift'][0]['check_in']);
    }
}
