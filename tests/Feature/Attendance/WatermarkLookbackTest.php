<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\SyncAccessLog;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi (production holding, 2026-09-22): sebagian karyawan tidak pernah masuk
 * attendance_records sama sekali. access_logs TIDAK punya id/created_at, jadi watermark cuma bisa
 * pakai access_datetime (jam scan di device) - baris yang baru MASUK ke database sesudah watermark
 * melewati jam scan-nya (device dorong data belakangan) tidak akan pernah kebaca lagi.
 * SyncAccessLogsAttendance sekarang membaca mundur beberapa menit dari watermark.
 */
class WatermarkLookbackTest extends TestCase
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

    private function fixedUser(string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'start_time' => '08:00:00', 'break_minutes' => 60,
            'min_work_minutes' => 0, 'end_time' => '16:00:00', 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        return $user;
    }

    public function test_scan_that_lands_in_access_logs_after_the_watermark_passed_is_still_processed(): void
    {
        $user = $this->fixedUser('LATE001');

        // Watermark sudah lewat 08:10 (scan orang lain), lalu baris scan 08:05 milik user ini baru
        // masuk ke access_logs - tanpa jendela mundur, baris ini tidak akan pernah kebaca.
        SyncAccessLog::watermark()->update(['last_synced_at' => '2026-08-06 08:10:00']);
        $this->scan($user, '2026-08-06 08:05:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'LATE001')->where('date', '2026-08-06')->first();
        $this->assertNotNull($record, 'Scan yang telat masuk ke access_logs harus tetap tercatat.');
        $this->assertSame('08:05:00', $record->check_in_time);
    }

    public function test_watermark_never_moves_backwards(): void
    {
        $user = $this->fixedUser('LATE002');

        SyncAccessLog::watermark()->update(['last_synced_at' => '2026-08-06 08:10:00']);
        $this->scan($user, '2026-08-06 08:05:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertSame(
            '2026-08-06 08:10:00',
            SyncAccessLog::watermark()->fresh()->last_synced_at->toDateTimeString(),
            'Scan lama tidak boleh menarik watermark mundur (run berikutnya jadi baca makin jauh ke belakang).'
        );
    }

    public function test_rerunning_the_same_scans_does_not_duplicate_rows(): void
    {
        $user = $this->fixedUser('LATE003');

        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 16:05:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        // Run kedua membaca ulang rentang yang sama lewat jendela mundur.
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $records = AttendanceRecords::where('nip', 'LATE003')->where('date', '2026-08-06')->get();
        $this->assertCount(1, $records);
        $this->assertSame('08:00:00', $records->first()->check_in_time);
        $this->assertSame('16:05:00', $records->first()->check_out_time);
    }
}
