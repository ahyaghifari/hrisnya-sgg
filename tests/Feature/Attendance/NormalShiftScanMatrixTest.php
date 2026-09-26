<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresi shift NORMAL (tanpa extra shift sama sekali) - jalur yang sudah jalan di production.
 * Hasilnya harus tetap sama sebelum & sesudah perubahan logika extra shift. Per-tick (cron tiap
 * menit) dan batch. Pagi 07-14, Siang 14-21, Malam 21-07 (overnight), buffer 30, toleransi telat 10.
 * Hari = 2026-08-10 + N ('N HH:MM' = scan, roster: N => tipe|libur).
 */
class NormalShiftScanMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function date(int $n): string
    {
        return Carbon::parse('2026-08-10')->addDays($n)->toDateString();
    }

    private function setUpWorld(array $roster): void
    {
        $company = Companies::create(['code' => 'NRM', 'name' => 'Normal Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'NRM001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $types = [];
        foreach (['pagi' => ['07:00:00', '14:00:00', false], 'siang' => ['14:00:00', '21:00:00', false], 'malam' => ['21:00:00', '07:00:00', true]] as $key => [$s, $e, $o]) {
            $types[$key] = ScheduleShiftType::create([
                'schedule_id' => $schedule->id, 'code' => strtoupper($key), 'name' => ucfirst($key), 'shift_type' => $key,
                'start_time' => $s, 'end_time' => $e, 'is_overnight' => $o,
                'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
            ]);
        }

        foreach ($roster as $n => $type) {
            ScheduleShiftAssignment::create([
                'company_id' => $company->id, 'user_id' => $this->user->id, 'date' => $this->date($n),
                'shift_type_id' => $type === 'libur' ? null : $types[$type]->id, 'off_day' => $type === 'libur',
            ]);
        }
    }

    private function datetime(string $scan): string
    {
        [$n, $time] = explode(' ', $scan);

        return $this->date((int) $n) . ' ' . $time . ':00';
    }

    private function insertScan(string $datetime): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => $datetime,
            'access_date' => substr($datetime, 0, 10), 'access_time' => substr($datetime, 11),
            'first_name' => $this->user->name, 'last_name' => '', 'person_name' => $this->user->name, 'person_group' => 'default',
        ]);
    }

    private function row(string $date, ?string $in, ?string $out): array
    {
        return [$date, $in === null ? null : substr($in, 0, 5), $out === null ? null : substr($out, 0, 5)];
    }

    public static function scenarios(): array
    {
        // [roster [N => tipe|libur], scan-scan ['N HH:MM'], record yang diharapkan [[N, in, out]]]
        return [
            'pagi normal' => [[0 => 'pagi'], ['0 07:00', '0 14:00'], [[0, '07:00', '14:00']]],
            'pagi datang 06:45 (dalam buffer)' => [[0 => 'pagi'], ['0 06:45', '0 14:00'], [[0, '06:45', '14:00']]],
            'pagi pulang 14:20' => [[0 => 'pagi'], ['0 07:00', '0 14:20'], [[0, '07:00', '14:20']]],
            'pagi hanya scan masuk' => [[0 => 'pagi'], ['0 07:00'], [[0, '07:00', null]]],
            'pagi 3 scan (pulang diulang 14:03)' => [[0 => 'pagi'], ['0 07:00', '0 14:00', '0 14:03'], [[0, '07:00', '14:03']]],
            'siang normal' => [[0 => 'siang'], ['0 14:00', '0 21:00'], [[0, '14:00', '21:00']]],
            'malam normal (overnight)' => [[0 => 'malam'], ['0 21:00', '1 07:00'], [[0, '21:00', '07:00']]],
            'malam datang 20:50 (dalam buffer)' => [[0 => 'malam'], ['0 20:50', '1 07:00'], [[0, '20:50', '07:00']]],
            'malam hanya scan masuk' => [[0 => 'malam'], ['0 21:00'], [[0, '21:00', null]]],
            'pagi dua hari berturut' => [[0 => 'pagi', 1 => 'pagi'], ['0 07:00', '0 14:00', '1 07:00', '1 14:00'], [[0, '07:00', '14:00'], [1, '07:00', '14:00']]],
            'malam dua hari berturut' => [[0 => 'malam', 1 => 'malam'], ['0 21:00', '1 07:00', '1 21:00', '2 07:00'], [[0, '21:00', '07:00'], [1, '21:00', '07:00']]],
            'malam lalu pagi lusa' => [[0 => 'malam', 2 => 'pagi'], ['0 21:00', '1 07:00', '2 07:00', '2 14:00'], [[0, '21:00', '07:00'], [2, '07:00', '14:00']]],
            'hari libur, scan diabaikan' => [[0 => 'libur'], ['0 08:00', '0 16:00'], []],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_per_tick(array $roster, array $scans, array $expected): void
    {
        $this->setUpWorld($roster);

        foreach ($scans as $scan) {
            $this->insertScan($this->datetime($scan));
            $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        }

        $rows = AttendanceRecords::where('nip', $this->user->nip)->orderBy('date')->get()
            ->map(fn ($r) => $this->row(substr((string) $r->date, 0, 10), $r->check_in_time, $r->check_out_time))->all();

        $this->assertSame(array_map(fn ($e) => $this->row($this->date($e[0]), $e[1], $e[2]), $expected), $rows);
        $this->assertSame(0, ExtraShiftAttendanceRecord::count());
    }

    #[DataProvider('scenarios')]
    public function test_batch(array $roster, array $scans, array $expected): void
    {
        $this->setUpWorld($roster);

        foreach ($scans as $scan) {
            $this->insertScan($this->datetime($scan));
        }

        $b = app(AccessLogScheduleReader::class)->readAndGroup($this->date(0), $this->date(3))['buckets'];

        $rows = collect($b['shift'])->sortBy('date')->map(fn ($e) => $this->row($e['date'], $e['check_in'], $e['check_out']))->values()->all();

        $this->assertSame(array_map(fn ($e) => $this->row($this->date($e[0]), $e[1], $e[2]), $expected), $rows);
        $this->assertSame([], $b['extra_shift']);
    }

    public function test_pagi_late_is_still_counted_as_late(): void
    {
        $this->setUpWorld([0 => 'pagi']);

        foreach (['0 07:20', '0 14:00'] as $scan) {
            $this->insertScan($this->datetime($scan));
            $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        }

        $record = AttendanceRecords::where('nip', $this->user->nip)->first();
        $this->assertSame('late', $record->status);
        $this->assertSame(10, (int) $record->late_minutes);
    }
}
