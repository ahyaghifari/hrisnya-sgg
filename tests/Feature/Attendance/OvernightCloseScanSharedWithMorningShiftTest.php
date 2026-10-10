<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Malam selesai 08:00 lalu hari itu juga Pagi 08:00 + extra Siang 14:00-20:00: scan 08:00 menutup
 * shift malam DAN jadi jam masuk Pagi, cukup 2 scan hari itu (08:00 & 20:00).
 */
class OvernightCloseScanSharedWithMorningShiftTest extends TestCase
{
    use RefreshDatabase;

    private function scan(User $u, string $dt): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $u->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10),
            'access_time' => substr($dt, 11), 'first_name' => $u->name, 'last_name' => '',
            'person_name' => $u->name, 'person_group' => 'default',
        ]);
    }

    private function setupDay(array $scans, bool $withExtra = true): array
    {
        $c = Companies::create(['code' => 'OVS', 'name' => 'T', 'location' => 'T', 'is_active' => true]);
        $u = User::factory()->create(['nip' => 'OVS001', 'company_id' => $c->id]);
        $s = Schedule::create(['company_id' => $c->id, 'name' => 'Shift', 'schedule_type' => 'shift']);
        $mk = fn ($code, $st, $en, $ov) => ScheduleShiftType::create([
            'schedule_id' => $s->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $st, 'end_time' => $en, 'is_overnight' => $ov,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $pagi = $mk('PAGI', '08:00:00', '14:00:00', false);
        $siang = $mk('SIANG', '14:00:00', '20:00:00', false);
        $malam = $mk('MALAM', '20:00:00', '08:00:00', true);

        $a = fn ($t, $d) => ScheduleShiftAssignment::create(['company_id' => $c->id, 'user_id' => $u->id, 'shift_type_id' => $t->id, 'date' => $d, 'off_day' => false]);
        $a($malam, '2026-10-08');
        $a($pagi, '2026-10-09');
        if ($withExtra) {
            ExtraShiftAssignment::create(['company_id' => $c->id, 'user_id' => $u->id, 'shift_type_id' => $siang->id, 'date' => '2026-10-09', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $u->id]);
        }

        foreach ($scans as $dt) {
            $this->scan($u, $dt);
        }

        return app(AccessLogScheduleReader::class)->readAndGroup('2026-10-08', '2026-10-09')['buckets'];
    }

    public function test_scan_that_closes_overnight_is_also_morning_check_in(): void
    {
        $b = $this->setupDay(['2026-10-08 20:00:00', '2026-10-09 08:00:00', '2026-10-09 20:00:00']);

        $this->assertSame('08:00:00', $b['shift'][0]['check_out']);
        $this->assertSame('2026-10-08', $b['shift'][0]['date']);

        $this->assertSame('2026-10-09', $b['shift'][1]['date']);
        $this->assertSame('08:00:00', $b['shift'][1]['check_in']);
        $this->assertSame('14:00:00', $b['shift'][1]['check_out']);

        $this->assertCount(1, $b['extra_shift']);
        $this->assertSame('14:00:00', $b['extra_shift'][0]['check_in']);
        $this->assertSame('20:00:00', $b['extra_shift'][0]['check_out']);
    }

    /** Cuma scan 08:00 itu hari itu: murni pulang shift malam, bukan masuk Pagi. */
    public function test_lone_closing_scan_is_not_a_check_in(): void
    {
        $b = $this->setupDay(['2026-10-08 20:00:00', '2026-10-09 08:00:00'], withExtra: false);

        $this->assertCount(1, $b['shift']);
        $this->assertSame('2026-10-08', $b['shift'][0]['date']);
        $this->assertSame('08:00:00', $b['shift'][0]['check_out']);
    }
}
