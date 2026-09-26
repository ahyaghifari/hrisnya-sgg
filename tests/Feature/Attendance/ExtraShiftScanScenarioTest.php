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
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriks skenario scan Extra Shift sebelum rilis. Setiap skenario dijalankan lewat DUA jalur:
 *  - per-tick  : scan masuk satu-satu, cron `attendance:sync-access-logs` jalan tiap scan (jalur
 *                production, cron tiap menit).
 *  - batch     : semua scan dibaca sekaligus lewat AccessLogScheduleReader::readAndGroup() (jalur
 *                tombol Sync Presensi).
 *
 * Aturan bisnis (instruksi user 2026-09-26):
 *  - Libur/kosong + extra shift: scan tetap diterima (tidak boleh dibuang / dianggap "off").
 *  - Shift menyambung (pagi<->siang): cukup 2 scan; batas antar shift sintetis dari jam config.
 *  - Shift tidak menyambung (pagi -> malam): 4 scan, 2 per shift.
 *  - Extra shift tidak pernah "terlambat".
 * Shift: Pagi 07-14, Siang 14-21, Malam 21-07 (overnight). Buffer check-in/out 30 menit.
 */
class ExtraShiftScanScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected const D0 = '2026-08-10';

    protected const D1 = '2026-08-11';

    /** @var array<string, ScheduleShiftType> */
    private array $types = [];

    protected User $user;

    protected function setUpWorld(): void
    {
        $company = Companies::create(['code' => 'SCN', 'name' => 'Scenario Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'SCN001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        foreach ([
            'pagi' => ['07:00:00', '14:00:00', false],
            'siang' => ['14:00:00', '21:00:00', false],
            'malam' => ['21:00:00', '07:00:00', true],
        ] as $key => [$start, $end, $overnight]) {
            $this->types[$key] = ScheduleShiftType::create([
                'schedule_id' => $schedule->id, 'code' => strtoupper($key), 'name' => ucfirst($key), 'shift_type' => $key,
                'start_time' => $start, 'end_time' => $end, 'is_overnight' => $overnight,
                'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30,
                'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
            ]);
        }
    }

    protected function seedRoster(string $roster, string $extra): void
    {
        $companyId = $this->user->company_id;

        if ($roster === 'libur') {
            ScheduleShiftAssignment::create(['company_id' => $companyId, 'user_id' => $this->user->id, 'shift_type_id' => null, 'date' => self::D0, 'off_day' => true]);
        } elseif ($roster !== 'kosong') {
            ScheduleShiftAssignment::create(['company_id' => $companyId, 'user_id' => $this->user->id, 'shift_type_id' => $this->types[$roster]->id, 'date' => self::D0, 'off_day' => false]);
        }

        ExtraShiftAssignment::create([
            'company_id' => $companyId, 'user_id' => $this->user->id, 'shift_type_id' => $this->types[$extra]->id,
            'date' => self::D0, 'status' => 'approved', 'source' => 'assigned', 'category' => 'extra_shift', 'created_by' => $this->user->id,
        ]);
    }

    /** 'HH:MM' = tanggal D0, 'HH:MM+1' = tanggal D1. */
    protected function datetime(string $scan): string
    {
        return str_ends_with($scan, '+1')
            ? self::D1 . ' ' . substr($scan, 0, 5) . ':00'
            : self::D0 . ' ' . $scan . ':00';
    }

    protected function insertScan(string $datetime): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip,
            'access_datetime' => $datetime,
            'access_date' => substr($datetime, 0, 10),
            'access_time' => substr($datetime, 11),
            'first_name' => $this->user->name,
            'last_name' => '',
            'person_name' => $this->user->name,
            'person_group' => 'default',
        ]);
    }

    protected function hm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    public static function scenarios(): array
    {
        // [roster normal, extra, scan-scan, normal yang diharapkan [in,out]|null, extra yang diharapkan [in,out]|null]
        return [
            // ---- libur/kosong -> extra: scan wajib diterima ----
            'libur -> pagi, tepat waktu' => ['libur', 'pagi', ['07:00', '14:00'], null, ['07:00', '14:00']],
            'libur -> pagi, datang 06:50 (dalam buffer)' => ['libur', 'pagi', ['06:50', '14:00'], null, ['06:50', '14:00']],
            'kosong -> pagi, datang 06:50 (dalam buffer)' => ['kosong', 'pagi', ['06:50', '14:00'], null, ['06:50', '14:00']],
            'libur -> siang, tepat waktu' => ['libur', 'siang', ['14:00', '21:00'], null, ['14:00', '21:00']],
            'libur -> siang, datang 13:50 (dalam buffer)' => ['libur', 'siang', ['13:50', '21:00'], null, ['13:50', '21:00']],
            'kosong -> siang, datang 13:50 (dalam buffer)' => ['kosong', 'siang', ['13:50', '21:00'], null, ['13:50', '21:00']],
            'libur -> malam, tepat waktu' => ['libur', 'malam', ['21:00', '07:00+1'], null, ['21:00', '07:00']],
            'libur -> malam, datang 20:50 (dalam buffer)' => ['libur', 'malam', ['20:50', '07:00+1'], null, ['20:50', '07:00']],
            'kosong -> malam, datang 20:50 (dalam buffer)' => ['kosong', 'malam', ['20:50', '07:00+1'], null, ['20:50', '07:00']],

            // ---- pagi -> malam (tidak menyambung, 4 scan) ----
            'pagi -> malam, 4 scan tepat waktu' => ['pagi', 'malam', ['07:00', '14:00', '21:00', '07:00+1'], ['07:00', '14:00'], ['21:00', '07:00']],
            'pagi -> malam, extra datang 20:50 (dalam buffer)' => ['pagi', 'malam', ['07:00', '14:00', '20:50', '07:00+1'], ['07:00', '14:00'], ['20:50', '07:00']],

            // ---- pagi -> siang (menyambung, normal duluan) ----
            'pagi -> siang, 2 scan' => ['pagi', 'siang', ['07:00', '21:00'], ['07:00', '14:00'], ['14:00', '21:00']],
            'pagi -> siang, 2 scan, pulang 21:20 (lewat batas normal)' => ['pagi', 'siang', ['07:00', '21:20'], ['07:00', '14:00'], ['14:00', '21:20']],
            'pagi -> siang, 4 scan (scan di transisi diabaikan)' => ['pagi', 'siang', ['07:00', '14:00', '14:05', '21:00'], ['07:00', '14:00'], ['14:00', '21:00']],
            'pagi -> siang, 3 scan (scan di transisi diabaikan)' => ['pagi', 'siang', ['07:00', '14:00', '21:00'], ['07:00', '14:00'], ['14:00', '21:00']],
            'pagi -> siang, extra tidak dikerjakan (pulang 14:00)' => ['pagi', 'siang', ['07:00', '14:00'], ['07:00', '14:00'], null],

            // ---- siang -> pagi (menyambung, extra duluan) ----
            'siang -> pagi, 2 scan' => ['siang', 'pagi', ['07:00', '21:00'], ['14:00', '21:00'], ['07:00', '14:00']],
            'siang -> pagi, 2 scan, datang 06:50 (dalam buffer)' => ['siang', 'pagi', ['06:50', '21:00'], ['14:00', '21:00'], ['06:50', '14:00']],
            'siang -> pagi, 4 scan (scan di transisi diabaikan)' => ['siang', 'pagi', ['07:00', '14:00', '14:05', '21:00'], ['14:00', '21:00'], ['07:00', '14:00']],
            'siang -> pagi, extra tidak dikerjakan (masuk 14:00 saja)' => ['siang', 'pagi', ['14:00', '21:00'], ['14:00', '21:00'], null],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_per_tick_production_path(string $roster, string $extra, array $scans, ?array $expectedNormal, ?array $expectedExtra): void
    {
        $this->setUpWorld();
        $this->seedRoster($roster, $extra);

        foreach ($scans as $scan) {
            $this->insertScan($this->datetime($scan));
            $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        }

        $normal = AttendanceRecords::where('nip', $this->user->nip)->get()
            ->map(fn ($r) => [$this->hm($r->check_in_time), $this->hm($r->check_out_time)])->values()->all();
        $extraRecords = ExtraShiftAttendanceRecord::all()
            ->map(fn ($r) => [$this->hm($r->check_in_time), $this->hm($r->check_out_time)])->values()->all();

        $this->assertSame($expectedNormal ? [$expectedNormal] : [], $normal, 'attendance_records (shift normal)');
        $this->assertSame($expectedExtra ? [$expectedExtra] : [], $extraRecords, 'extra_shift_attendance_records');
    }

    #[DataProvider('scenarios')]
    public function test_batch_read_path(string $roster, string $extra, array $scans, ?array $expectedNormal, ?array $expectedExtra): void
    {
        $this->setUpWorld();
        $this->seedRoster($roster, $extra);

        foreach ($scans as $scan) {
            $this->insertScan($this->datetime($scan));
        }

        $result = app(AccessLogScheduleReader::class)->readAndGroup(self::D0, self::D1);
        $b = $result['buckets'];

        $this->assertSame([], $b['off'], 'scan dibuang ke bucket off');
        $this->assertSame([], $result['unresolved_schedule'], 'scan ditolak: jadwal tidak ditemukan');

        $pair = fn (array $e) => [$this->hm($e['check_in']), $this->hm($e['check_out'])];

        $this->assertSame($expectedNormal ? [$expectedNormal] : [], array_map($pair, $b['shift']), 'bucket shift (normal)');
        $this->assertSame($expectedExtra ? [$expectedExtra] : [], array_map($pair, $b['extra_shift']), 'bucket extra_shift');
    }

    /** Extra shift TIDAK ADA konsep terlambat (instruksi user): datang jauh setelah jam mulai
     * pun late_minutes harus 0 dan status bukan late. */
    #[DataProvider('lateScenarios')]
    public function test_extra_shift_is_never_late(string $roster, string $extra, array $scans): void
    {
        $this->setUpWorld();
        $this->seedRoster($roster, $extra);

        foreach ($scans as $scan) {
            $this->insertScan($this->datetime($scan));
            $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        }

        $record = ExtraShiftAttendanceRecord::first();
        $this->assertNotNull($record, 'extra shift tidak tercatat');
        $this->assertSame(0, (int) $record->late_minutes, 'late_minutes extra shift');
        $this->assertNotSame('late', $record->status, 'status extra shift');
    }

    public static function lateScenarios(): array
    {
        return [
            'libur -> pagi, datang 07:40' => ['libur', 'pagi', ['07:40', '14:00']],
            'siang -> pagi, datang 07:40' => ['siang', 'pagi', ['07:40', '14:00', '14:05', '21:00']],
        ];
    }
}
