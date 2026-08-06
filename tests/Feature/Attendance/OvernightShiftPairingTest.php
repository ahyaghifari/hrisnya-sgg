<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shift 23:00-08:00 (is_overnight=true): scan check-in jatuh di access_date D, scan check-out
 * jatuh di access_date D+1 - dulu ini bikin 2 entri terpisah salah (checkout dibaca sebagai
 * check-in hari berikutnya). Cover pairScansForEmployee() (batch) dan lookback incremental di
 * AccessLogScheduleReader.
 */
class OvernightShiftPairingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOvernightShiftUser(): User
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP001', 'company_id' => $company->id]);

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Shift Malam',
            'schedule_type' => 'shift',
        ]);

        $shiftType = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'MLM',
            'name' => 'Malam',
            'start_time' => '23:00:00',
            'end_time' => '08:00:00',
            'is_overnight' => true,
            'break_minutes' => 60,
            'checkin_buffer_minutes' => 60,
            'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 0,
            'counted_work_days' => 1,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $shiftType->id,
            'date' => '2026-08-06',
            'off_day' => false,
        ]);

        return $user;
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

    public function test_batch_read_pairs_overnight_checkin_and_next_day_checkout(): void
    {
        $user = $this->makeOvernightShiftUser();
        $this->insertScan($user, '2026-08-06 23:10:00');
        $this->insertScan($user, '2026-08-07 07:50:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-07');

        $this->assertCount(1, $result['buckets']['shift']);
        $entry = $result['buckets']['shift'][0];
        $this->assertSame('2026-08-06', $entry['date']);
        $this->assertSame('23:10:00', $entry['check_in']);
        $this->assertSame('07:50:00', $entry['check_out']);

        // Tanggal 07 tidak dianggap punya check-in sendiri dari scan yang "dipinjam".
        $this->assertCount(0, $result['unresolved_schedule']);
    }

    public function test_incremental_read_attaches_next_day_scan_as_previous_day_checkout(): void
    {
        $user = $this->makeOvernightShiftUser();
        $reader = app(AccessLogScheduleReader::class);

        // Batch 1: cuma check-in yang sudah ke-sync (checkout belum terjadi).
        $this->insertScan($user, '2026-08-06 23:10:00');
        $first = $reader->readAndGroupIncremental(null);
        $this->assertCount(1, $first['buckets']['shift']);
        $this->assertSame('23:10:00', $first['buckets']['shift'][0]['check_in']);
        $this->assertNull($first['buckets']['shift'][0]['check_out']);

        app(\App\Repositories\Attendance\AttendanceRecordRepository::class)
            ->saveCalculatedResult(
                app(\App\Services\Attendance\Calculators\ShiftScheduleCalculator::class)
                    ->calculate($first['buckets']['shift'])[0],
                'access_logs_shift'
            );

        // Batch 2: checkout jatuh di access_date besok - harus nyambung ke record tanggal 06.
        $this->insertScan($user, '2026-08-07 07:50:00');
        $second = $reader->readAndGroupIncremental($first['max_synced_at']);

        $this->assertCount(1, $second['buckets']['shift']);
        $entry = $second['buckets']['shift'][0];
        $this->assertSame('2026-08-06', $entry['date']);
        $this->assertSame('23:10:00', $entry['check_in']);
        $this->assertSame('07:50:00', $entry['check_out']);
        $this->assertTrue($entry['is_new_checkout']);
    }
}
