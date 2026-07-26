<?php

namespace Tests\Feature;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Notifications\AttendanceScanRejected;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Test notifikasi baru yang dikirim dari pipeline access_logs -> attendance_records:
 * check-in, check-out (sekali per event, bukan tiap batch incremental), dan penolakan scan
 * kalau jadwal user tidak ditemukan (AccessLogScheduleReader::unresolved_schedule).
 */
class AttendanceAccessLogNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function insertScan(string $nip, string $date, string $time): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $nip,
            'access_datetime' => $date . ' ' . $time,
            'access_date' => $date,
            'access_time' => $time,
            'first_name' => 'Test',
            'last_name' => 'User',
            'person_name' => 'Test User',
            'person_group' => 'Employee',
        ]);
    }

    public function test_sends_checked_in_then_checked_out_notification_once_each(): void
    {
        Notification::fake();

        $company = Companies::create(['code' => 'CPA', 'name' => 'Company A', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP001']);

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
            'end_time' => '17:00:00',
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $date->copy()->subMonth()->toDateString(),
        ]);

        // Batch 1: cuma scan check-in.
        $this->insertScan($user->nip, $date->toDateString(), '08:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        Notification::assertSentTo($user, AttendanceCheckedIn::class);
        Notification::assertNotSentTo($user, AttendanceCheckedOut::class);

        // Batch 2: scan check-out (menit berikutnya, request cron baru).
        $this->insertScan($user->nip, $date->toDateString(), '17:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        Notification::assertSentTo($user, AttendanceCheckedOut::class);
        // Check-in cuma dikirim 1x total (bukan terkirim lagi di batch ke-2).
        Notification::assertSentToTimes($user, AttendanceCheckedIn::class, 1);
    }

    public function test_sends_scan_rejected_notification_when_schedule_not_found(): void
    {
        Notification::fake();

        $company = Companies::create(['code' => 'CPB', 'name' => 'Company B', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP002']);
        // Sengaja TIDAK dibuatkan ScheduleAssignment sama sekali.

        $date = Carbon::parse('2026-08-04');
        $this->insertScan($user->nip, $date->toDateString(), '08:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        Notification::assertSentTo($user, AttendanceScanRejected::class);
        $this->assertDatabaseMissing('attendance_records', ['nip' => $user->nip]);
    }
}
