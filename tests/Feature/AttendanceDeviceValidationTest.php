<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * attendance_devices = daftar device absensi terdaftar per company. AccessLogScheduleReader
 * memblokir total scan yang device_name-nya (access_logs) tidak cocok dengan device aktif
 * manapun milik company user - bukan sekadar ditandai, baris itu tidak pernah sampai ke
 * attendance_records, sama seperti jalur unresolved_schedule (lihat SyncAccessLogsAttendance).
 */
class AttendanceDeviceValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeFlexibleUser(Companies $company, string $nip): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Flexible Test',
            'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00',
            'flexible_checkin_until' => '10:00:00',
        ]);

        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => Carbon::parse('2026-08-03')->dayOfWeek,
            'min_work_minutes' => 240,
            'max_work_minutes' => 480,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => Carbon::parse('2026-08-03')->subMonth()->toDateString(),
        ]);

        return $user;
    }

    private function insertScan(User $user, Carbon $date, string $time, ?string $deviceName): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $user->nip,
            'device_name' => $deviceName,
            'access_datetime' => $date->toDateString() . ' ' . $time,
            'access_date' => $date->toDateString(),
            'access_time' => $time,
            'first_name' => 'Test',
            'last_name' => 'User',
            'person_name' => 'Test User',
            'person_group' => 'Employee',
        ]);
    }

    public function test_unregistered_device_is_blocked_and_not_saved(): void
    {
        $company = Companies::create(['code' => 'CPA', 'name' => 'Company A', 'location' => 'Test', 'is_active' => true]);
        AttendanceDevice::create(['company_id' => $company->id, 'name' => 'Pintu Utama', 'is_active' => true]);

        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFlexibleUser($company, 'EMP001');

        $this->insertScan($user, $date, '08:00:00', 'Device Tidak Dikenal');
        $this->insertScan($user, $date, '13:00:00', 'Device Tidak Dikenal');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertNull(
            AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->first()
        );
    }

    public function test_registered_device_is_processed_normally(): void
    {
        $company = Companies::create(['code' => 'CPB', 'name' => 'Company B', 'location' => 'Test', 'is_active' => true]);
        AttendanceDevice::create(['company_id' => $company->id, 'name' => 'Pintu Utama', 'is_active' => true]);

        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFlexibleUser($company, 'EMP002');

        $this->insertScan($user, $date, '08:00:00', 'Pintu Utama');
        $this->insertScan($user, $date, '13:00:00', 'Pintu Utama');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->firstOrFail();

        $this->assertSame('08:00:00', $record->check_in_time);
    }

    public function test_device_from_a_different_company_is_blocked(): void
    {
        $companyA = Companies::create(['code' => 'CPA2', 'name' => 'Company A2', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'CPB2', 'name' => 'Company B2', 'location' => 'Test', 'is_active' => true]);
        AttendanceDevice::create(['company_id' => $companyB->id, 'name' => 'Pintu Cabang B', 'is_active' => true]);

        $date = Carbon::parse('2026-08-03');
        $user = $this->makeFlexibleUser($companyA, 'EMP003');

        $this->insertScan($user, $date, '08:00:00', 'Pintu Cabang B');
        $this->insertScan($user, $date, '13:00:00', 'Pintu Cabang B');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertNull(
            AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->first()
        );
    }
}
