<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Jadwal Fixed/Flexible TIDAK PERNAH kenal extra shift - hasil pairing/presensinya harus sama
 * persis dengan atau tanpa baris ExtraShiftAssignment yang nyasar (mis. salah input). Cover jalur
 * per-tick (cron tiap menit, production) dan batch (tombol Sync Presensi). Senin 2026-08-10.
 */
class FixedFlexibleUnaffectedByExtraShiftTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-10';

    private User $user;

    private function setUpUser(string $type, bool $strayExtra): void
    {
        $company = Companies::create(['code' => 'FF1', 'name' => 'FF Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'FF001', 'company_id' => $company->id]);

        if ($type === 'fixed') {
            $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'break_minutes' => 60, 'min_work_minutes' => 0, 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
                'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
            ]);
        } else {
            $schedule = Schedule::create([
                'company_id' => $company->id, 'name' => 'Fleksibel', 'schedule_type' => 'flexible',
                'flexible_checkin_from' => '06:00:00', 'flexible_checkin_until' => '10:00:00',
            ]);
            ScheduleFlexibleDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'break_minutes' => 60,
                'min_work_minutes' => 420, 'max_work_minutes' => 480, 'counted_work_days' => 1, 'late_checkin_deadline' => '09:00:00',
            ]);
        }

        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $this->user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        if ($strayExtra) {
            $shiftSchedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
            $malam = ScheduleShiftType::create([
                'schedule_id' => $shiftSchedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => 'malam',
                'start_time' => '21:00:00', 'end_time' => '07:00:00', 'is_overnight' => true,
                'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
            ]);
            ExtraShiftAssignment::create([
                'company_id' => $company->id, 'user_id' => $this->user->id, 'shift_type_id' => $malam->id,
                'date' => self::DATE, 'status' => 'approved', 'source' => 'assigned', 'category' => 'extra_shift', 'created_by' => $this->user->id,
            ]);
        }
    }

    private function insertScan(string $time): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => self::DATE . ' ' . $time . ':00',
            'access_date' => self::DATE, 'access_time' => $time . ':00',
            'first_name' => $this->user->name, 'last_name' => '', 'person_name' => $this->user->name, 'person_group' => 'default',
        ]);
    }

    private function hm(?string $t): ?string
    {
        return $t === null ? null : substr($t, 0, 5);
    }

    public static function cases(): array
    {
        $rows = [];

        foreach (['fixed', 'flexible'] as $type) {
            foreach ([false, true] as $stray) {
                $label = $type . ($stray ? ' + extra shift nyasar' : ' tanpa extra');
                $rows["$label: masuk & pulang"] = [$type, $stray, ['08:00', '16:00'], ['08:00', '16:00']];
                $rows["$label: pulang 20:00 (lewat buffer)"] = [$type, $stray, ['08:00', '20:00'], ['08:00', '20:00']];
            }
        }

        // Khusus Fixed: scan tunggal di jam pulang dibaca sebagai check-out (lupa scan masuk).
        foreach ([false, true] as $stray) {
            $rows['fixed' . ($stray ? ' + extra shift nyasar' : ' tanpa extra') . ': lupa scan masuk'] = ['fixed', $stray, ['16:05'], [null, '16:05']];
        }

        return $rows;
    }

    #[DataProvider('cases')]
    public function test_per_tick(string $type, bool $stray, array $scans, array $expected): void
    {
        $this->setUpUser($type, $stray);

        foreach ($scans as $time) {
            $this->insertScan($time);
            $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        }

        $rows = AttendanceRecords::where('nip', $this->user->nip)->get()
            ->map(fn ($r) => [$this->hm($r->check_in_time), $this->hm($r->check_out_time)])->all();

        $this->assertSame([$expected], $rows);
        $this->assertSame(0, ExtraShiftAttendanceRecord::count(), 'Fixed/Flexible tidak boleh menghasilkan record extra shift');
    }

    #[DataProvider('cases')]
    public function test_batch(string $type, bool $stray, array $scans, array $expected): void
    {
        $this->setUpUser($type, $stray);

        foreach ($scans as $time) {
            $this->insertScan($time);
        }

        $b = app(AccessLogScheduleReader::class)->readAndGroup(self::DATE, self::DATE)['buckets'];

        $entries = $b[$type];
        $this->assertCount(1, $entries);

        // Scan tunggal: reader hanya menyerahkan jam masuk + scan_count=1, calculator Fixed yang
        // memutuskan itu sebenarnya pulang (scan >= jam pulang jadwal).
        $entry = $entries[0];
        if ($type === 'fixed' && $entry['scan_count'] === 1) {
            $entry = app(\App\Services\Attendance\Calculators\FixedScheduleCalculator::class)->calculate([$entry])[0];
        }

        $this->assertSame($expected, [$this->hm($entry['check_in']), $this->hm($entry['check_out'])]);
        $this->assertSame([], $b['extra_shift']);
        $this->assertSame([], $b['shift']);
    }
}
