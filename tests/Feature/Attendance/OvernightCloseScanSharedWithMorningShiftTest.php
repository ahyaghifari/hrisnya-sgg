<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\AttendanceResyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Malam selesai 08:00 lalu hari itu juga Pagi 08:00 + extra Siang 14:00-20:00: scan 08:00 menutup
 * shift malam DAN jadi jam masuk Pagi, cukup 2 scan hari itu (08:00 & 20:00) - termasuk saat baru
 * scan 08:00 (belum pulang).
 */
class OvernightCloseScanSharedWithMorningShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function scan(string $dt): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10),
            'access_time' => substr($dt, 11), 'first_name' => $this->user->name, 'last_name' => '',
            'person_name' => $this->user->name, 'person_group' => 'default',
        ]);
    }

    /** Malam 2026-10-08, Pagi 2026-10-09 (+ extra Siang kalau $withExtra). */
    private function setupSchedule(bool $withExtra = true): void
    {
        $c = Companies::create(['code' => 'OVS', 'name' => 'T', 'location' => 'T', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'OVS001', 'company_id' => $c->id]);
        $s = Schedule::create(['company_id' => $c->id, 'name' => 'Shift', 'schedule_type' => 'shift']);
        $mk = fn ($code, $st, $en, $ov) => ScheduleShiftType::create([
            'schedule_id' => $s->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $st, 'end_time' => $en, 'is_overnight' => $ov,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $pagi = $mk('PAGI', '08:00:00', '14:00:00', false);
        $siang = $mk('SIANG', '14:00:00', '20:00:00', false);
        $malam = $mk('MALAM', '20:00:00', '08:00:00', true);

        $a = fn ($t, $d) => ScheduleShiftAssignment::create(['company_id' => $c->id, 'user_id' => $this->user->id, 'shift_type_id' => $t->id, 'date' => $d, 'off_day' => false]);
        $a($malam, '2026-10-08');
        $a($pagi, '2026-10-09');
        if ($withExtra) {
            ExtraShiftAssignment::create(['company_id' => $c->id, 'user_id' => $this->user->id, 'shift_type_id' => $siang->id, 'date' => '2026-10-09', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $this->user->id]);
        }
    }

    private function batch(): array
    {
        return app(AccessLogScheduleReader::class)->readAndGroup('2026-10-08', '2026-10-09')['buckets'];
    }

    private function record(string $date): ?AttendanceRecords
    {
        return AttendanceRecords::where('nip', 'OVS001')->where('date', $date)->first();
    }

    public function test_scan_that_closes_overnight_is_also_morning_check_in(): void
    {
        $this->setupSchedule();
        array_map($this->scan(...), ['2026-10-08 20:00:00', '2026-10-09 08:00:00', '2026-10-09 20:00:00']);
        $b = $this->batch();

        $this->assertSame('08:00:00', $b['shift'][0]['check_out']);
        $this->assertSame('2026-10-08', $b['shift'][0]['date']);

        $this->assertSame('2026-10-09', $b['shift'][1]['date']);
        $this->assertSame('08:00:00', $b['shift'][1]['check_in']);
        $this->assertSame('14:00:00', $b['shift'][1]['check_out']);

        $this->assertCount(1, $b['extra_shift']);
        $this->assertSame('14:00:00', $b['extra_shift'][0]['check_in']);
        $this->assertSame('20:00:00', $b['extra_shift'][0]['check_out']);
    }

    /** Baru scan 08:00 (belum pulang): malam tertutup DAN masuk Pagi sudah tercatat. */
    public function test_batch_only_the_morning_scan_closes_overnight_and_checks_in(): void
    {
        $this->setupSchedule();
        array_map($this->scan(...), ['2026-10-08 20:00:00', '2026-10-09 08:00:00']);
        $b = $this->batch();

        $this->assertSame('08:00:00', $b['shift'][0]['check_out']);
        $this->assertSame('2026-10-09', $b['shift'][1]['date']);
        $this->assertSame('08:00:00', $b['shift'][1]['check_in']);
        $this->assertNull($b['shift'][1]['check_out']);
    }

    public function test_incremental_morning_scan_closes_overnight_and_checks_in(): void
    {
        $this->setupSchedule();

        $this->scan('2026-10-08 20:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        $this->scan('2026-10-09 08:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertSame('08:00:00', $this->record('2026-10-08')->check_out_time);
        $this->assertSame('08:00:00', $this->record('2026-10-09')?->check_in_time, 'masuk Pagi harus tercatat dari scan yang sama');
    }

    /** Hitung ulang dari Profil Saya (AttendanceResyncService). */
    public function test_profile_resync_detects_morning_check_in(): void
    {
        $this->setupSchedule();
        array_map($this->scan(...), ['2026-10-08 20:00:00', '2026-10-09 08:00:00']);

        app(AttendanceResyncService::class)->resyncUserRange($this->user, '2026-10-08', '2026-10-09');

        $this->assertSame('08:00:00', $this->record('2026-10-08')->check_out_time);
        $this->assertSame('08:00:00', $this->record('2026-10-09')?->check_in_time);
    }

    /** Scan 08:00 di luar jendela masuk shift hari itu (Siang 14:00): murni pulang malam. */
    public function test_closing_scan_before_later_shift_window_is_not_a_check_in(): void
    {
        $this->setupSchedule(withExtra: false);
        ScheduleShiftAssignment::where('date', '2026-10-09')->update([
            'shift_type_id' => ScheduleShiftType::where('code', 'SIANG')->value('id'),
        ]);
        array_map($this->scan(...), ['2026-10-08 20:00:00', '2026-10-09 08:00:00']);
        $b = $this->batch();

        $this->assertCount(1, $b['shift']);
        $this->assertSame('2026-10-08', $b['shift'][0]['date']);
        $this->assertSame('08:00:00', $b['shift'][0]['check_out']);
    }
}
