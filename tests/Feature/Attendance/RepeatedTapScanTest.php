<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi (production, 2026-09-22): device fingerprint mencatat 1 tap jadi 2 baris berselang 1-2
 * detik, dan pasangan itu langsung jadi check-in + check-out berdurasi ~0. Jadwal Shift paling
 * kena karena ScheduleShiftType tidak punya min_work_minutes seperti Fixed/Flexible, jadi
 * pagarnya dipasang di level scan mentah (AccessLogScheduleReader::dropRepeatedTaps).
 */
class RepeatedTapScanTest extends TestCase
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

    private function shiftUser(string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift '.$nip, 'schedule_type' => 'shift']);
        $type = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '08:00:00', 'end_time' => '14:00:00', 'is_overnight' => false, 'break_minutes' => 0,
            'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => '2026-08-06', 'off_day' => false]);

        return $user;
    }

    private function fixedUser(string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Kantor '.$nip, 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'start_time' => '08:00:00', 'break_minutes' => 0,
            'min_work_minutes' => 0, 'end_time' => '16:00:00', 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        return $user;
    }

    public function test_shift_repeated_tap_does_not_become_a_checkout(): void
    {
        $user = $this->shiftUser('SHDBL');
        $this->scan($user, '2026-08-06 08:01:12');
        $this->scan($user, '2026-08-06 08:01:13');

        foreach ([
            'incremental' => app(AccessLogScheduleReader::class)->readAndGroupIncremental(null),
            'batch' => app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06'),
        ] as $label => $result) {
            $entry = $result['buckets']['shift'][0];
            $this->assertSame('08:01:12', $entry['check_in'], $label);
            $this->assertNull($entry['check_out'], "{$label}: tap ganda 1 detik tidak boleh jadi jam pulang.");
        }
    }

    public function test_fixed_repeated_tap_does_not_become_a_checkout(): void
    {
        $user = $this->fixedUser('FIXDBL');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 08:00:02');

        $entry = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06')['buckets']['fixed'][0];

        $this->assertSame('08:00:00', $entry['check_in']);
        $this->assertNull($entry['check_out']);
    }

    /** Jarak wajar (jam pulang beneran) tetap dipasangkan seperti biasa. */
    public function test_genuine_checkout_later_in_the_day_still_pairs(): void
    {
        $user = $this->shiftUser('SHOK');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 08:00:01'); // pantulan tap masuk
        $this->scan($user, '2026-08-06 14:02:00'); // pulang beneran

        $entry = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06')['buckets']['shift'][0];

        $this->assertSame('08:00:00', $entry['check_in']);
        $this->assertSame('14:02:00', $entry['check_out']);
    }

    /** Tap ganda PAS jam pulang: jam pulang tetap terisi dari tap pertamanya. */
    public function test_repeated_tap_at_checkout_keeps_the_first_tap_time(): void
    {
        $user = $this->shiftUser('SHOUT');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 14:00:00');
        $this->scan($user, '2026-08-06 14:00:03');

        $entry = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06')['buckets']['shift'][0];

        $this->assertSame('08:00:00', $entry['check_in']);
        $this->assertSame('14:00:00', $entry['check_out']);
    }
}
