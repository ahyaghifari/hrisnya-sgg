<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Aturan device (instruksi user 2026-10-10): scan diterima kalau device-nya TERDAFTAR & aktif di
 * attendance_devices, siapa pun pemiliknya (company lain boleh). Hanya device yang tidak terdaftar
 * sama sekali yang ditolak - lihat AccessLogScanReader::splitRejectedDevices().
 */
class UnrecognizedDeviceScanTest extends TestCase
{
    use RefreshDatabase;

    private function scan(User $u, string $dt, string $deviceName): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $u->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10), 'access_time' => substr($dt, 11),
            'first_name' => $u->name, 'last_name' => '', 'person_name' => $u->name, 'person_group' => 'default',
            'device_name' => $deviceName,
        ]);
    }

    private function fixedUser(Companies $company, string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor '.$nip, 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'start_time' => '08:00:00', 'break_minutes' => 0,
            'min_work_minutes' => 0, 'end_time' => '16:00:00', 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        return $user;
    }
    public function test_scan_from_another_companys_registered_device_is_accepted(): void
    {
        $companyA = Companies::create(['code' => 'CA', 'name' => 'Company A', 'location' => 'Jakarta']);
        $companyB = Companies::create(['code' => 'CB', 'name' => 'Company B', 'location' => 'Jakarta']);

        AttendanceDevice::create(['company_id' => $companyA->id, 'name' => 'Gerbang A', 'is_active' => true]);
        AttendanceDevice::create(['company_id' => $companyB->id, 'name' => 'Gerbang B', 'is_active' => true]);

        $user = $this->fixedUser($companyA, 'FIXFOR');

        // Pagi tap di device TERDAFTAR milik company lain, sore di device company sendiri - keduanya
        // sah (instruksi user 2026-10-10: beda device tidak masalah selama device-nya terdaftar).
        $this->scan($user, '2026-08-06 08:00:00', 'Gerbang B');
        $this->scan($user, '2026-08-06 16:00:00', 'Gerbang A');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06');

        $this->assertEmpty($result['rejected_device']);
        $entry = $result['buckets']['fixed'][0];
        $this->assertSame('08:00:00', $entry['check_in']);
        $this->assertSame('16:00:00', $entry['check_out']);
    }

    public function test_scan_from_an_unregistered_device_is_rejected(): void
    {
        $companyA = Companies::create(['code' => 'CA', 'name' => 'Company A', 'location' => 'Jakarta']);

        AttendanceDevice::create(['company_id' => $companyA->id, 'name' => 'Gerbang A', 'is_active' => true]);

        $user = $this->fixedUser($companyA, 'FIXALL');

        // Pagi di device tak terdaftar (ditolak), sore di device terdaftar (diterima).
        $this->scan($user, '2026-08-06 08:00:00', 'Gerbang Misterius');
        $this->scan($user, '2026-08-06 16:00:00', 'Gerbang A');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-06', '2026-08-06');

        $this->assertCount(1, $result['rejected_device']);
        $this->assertSame(['Gerbang Misterius'], $result['rejected_device'][0]['device_names']);
        $entry = app(\App\Services\Attendance\Calculators\FixedScheduleCalculator::class)->calculate($result['buckets']['fixed'])[0];
        $this->assertNull($entry['check_in']);
        $this->assertSame('16:00:00', $entry['check_out']);
    }
}
