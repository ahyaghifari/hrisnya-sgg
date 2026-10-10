<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cron mengirim kalimat sesuai keadaan: izin terlambat yang disetujui terbaca dari pengajuan
 * izin, dan extra/double shift (yang sebelumnya tanpa notifikasi) kini ikut dikabari.
 */
class AttendanceNotificationWordingTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-10'; // Senin

    private function scan(User $user, string $time): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $user->nip, 'access_datetime' => self::DATE . ' ' . $time . ':00',
            'access_date' => self::DATE, 'access_time' => $time . ':00',
            'first_name' => $user->name, 'last_name' => '', 'person_name' => $user->name, 'person_group' => 'default',
        ]);
    }

    private function bodies(User $user, string $class): array
    {
        return Notification::sent($user, $class)->map(fn ($n) => $n->toDatabase($user)['body'])->all();
    }

    public function test_late_check_in_with_approved_permission_says_so(): void
    {
        Notification::fake();
        $company = Companies::create(['code' => 'NW1', 'name' => 'NW', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => 'NW001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
            'break_minutes' => 60, 'min_work_minutes' => 0, 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => 'approved']);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'permission_type' => 'terlambat', 'date' => self::DATE]);

        $this->scan($user, '08:40');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $bodies = $this->bodies($user, AttendanceCheckedIn::class);
        $this->assertCount(1, $bodies);
        $this->assertStringContainsString('terlambat 30 menit dari jadwal', $bodies[0]);
        $this->assertStringContainsString('sudah memiliki izin yang disetujui', $bodies[0]);
    }

    /** Senin, tepat waktu: pengingat Corporate Value hanya untuk karyawan company holding (kode 0000). */
    public function test_corporate_value_reminder_is_for_holding_on_monday_only(): void
    {
        Notification::fake();

        $users = [];
        foreach (['0000' => 'NW901', 'OTH1' => 'NW902'] as $code => $nip) {
            $company = Companies::create(['code' => $code, 'name' => "Co {$code}", 'location' => 'Test', 'is_active' => true]);
            $user = User::factory()->create(['nip' => $nip, 'company_id' => $company->id]);
            $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'break_minutes' => 60, 'min_work_minutes' => 0, 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
                'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
            ]);
            ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);
            $this->scan($user, '07:58');
            $users[$code] = $user;
        }

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertStringContainsString('Jangan lupa Corporate Value', $this->bodies($users['0000'], AttendanceCheckedIn::class)[0]);
        $this->assertStringNotContainsString('Corporate Value', $this->bodies($users['OTH1'], AttendanceCheckedIn::class)[0]);
    }

    public function test_double_shift_gets_its_own_wording(): void
    {
        Notification::fake();
        $company = Companies::create(['code' => 'NW2', 'name' => 'NW2', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => 'NW002', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift', 'schedule_type' => 'shift']);
        $make = fn (string $code, string $start, string $end) => ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => false,
            'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $pagi = $make('Pagi', '08:00:00', '14:00:00');
        $siang = $make('Siang', '14:00:00', '20:00:00');

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => self::DATE, 'off_day' => false]);
        ExtraShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => self::DATE,
            'status' => 'approved', 'source' => 'swap_conflict', 'category' => 'double_shift', 'created_by' => $user->id,
        ]);

        $this->scan($user, '07:50');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();
        $this->scan($user, '20:30');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $in = implode(' | ', $this->bodies($user, AttendanceCheckedIn::class));
        $out = implode(' | ', $this->bodies($user, AttendanceCheckedOut::class));

        $this->assertStringContainsString('shift Pagi Anda', $in);
        $this->assertStringContainsString('shift lanjutan (double shift) Siang', $in);
        $this->assertStringContainsString('shift lanjutan (double shift) Siang', $out);
    }
}
