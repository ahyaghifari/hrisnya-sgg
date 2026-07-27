<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * schedule_fixed_days.min_work_minutes (baru) - beda dari out_of_window yang cuma menandai,
 * di sini scan checkout yang durasinya kurang dari minimum BENAR-BENAR ditolak: check_out_time
 * tidak ikut tersimpan sampai ada scan checkout berikutnya yang cukup (lihat
 * FixedScheduleCalculator + SyncAccessLogsAttendance).
 */
class FixedScheduleMinWorkMinutesTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixedUser(Companies $company, string $nip, int $minWorkMinutes): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Fixed Test',
            'schedule_type' => 'fixed',
        ]);

        $date = Carbon::parse('2026-08-03'); // Senin
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'checkin_buffer_minutes' => 180,
            'checkout_buffer_minutes' => 180,
            'min_work_minutes' => $minWorkMinutes,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $date->copy()->subMonth()->toDateString(),
        ]);

        return $user;
    }

    private function insertScan(User $user, Carbon $date, string $time): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $user->nip,
            'access_datetime' => $date->toDateString() . ' ' . $time,
            'access_date' => $date->toDateString(),
            'access_time' => $time,
            'first_name' => 'Test',
            'last_name' => 'User',
            'person_name' => 'Test User',
            'person_group' => 'Employee',
        ]);
    }

    public function test_checkout_below_minimum_work_duration_is_rejected(): void
    {
        $company = Companies::create(['code' => 'FMW', 'name' => 'Company FMW', 'location' => 'Test', 'is_active' => true]);
        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFixedUser($company, 'FIX001', minWorkMinutes: 240);

        // Checkin 08:00, checkout 08:30 -> cuma 30 menit, di bawah minimum 240 -> ditolak.
        $this->insertScan($user, $date, '08:00:00');
        $this->insertScan($user, $date, '08:30:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->firstOrFail();

        $this->assertSame('08:00:00', $record->check_in_time);
        $this->assertNull($record->check_out_time);
    }

    public function test_later_valid_checkout_is_accepted_after_earlier_rejection(): void
    {
        $company = Companies::create(['code' => 'FMW2', 'name' => 'Company FMW2', 'location' => 'Test', 'is_active' => true]);
        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFixedUser($company, 'FIX002', minWorkMinutes: 240);

        $this->insertScan($user, $date, '08:00:00');
        $this->insertScan($user, $date, '08:30:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        // Scan checkout valid berikutnya, batch terpisah (simulasi cron menit berikutnya).
        $this->insertScan($user, $date, '16:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->firstOrFail();

        $this->assertSame('08:00:00', $record->check_in_time);
        $this->assertSame('16:00:00', $record->check_out_time);
    }

    public function test_min_work_minutes_zero_disables_rejection(): void
    {
        $company = Companies::create(['code' => 'FMW3', 'name' => 'Company FMW3', 'location' => 'Test', 'is_active' => true]);
        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFixedUser($company, 'FIX003', minWorkMinutes: 0);

        $this->insertScan($user, $date, '08:00:00');
        $this->insertScan($user, $date, '08:30:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->firstOrFail();

        $this->assertSame('08:30:00', $record->check_out_time);
    }
}
