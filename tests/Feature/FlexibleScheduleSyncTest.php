<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceRecords;
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
 * Test gap yang baru ditambal: FlexibleScheduleCalculator sudah menghitung meets_target &
 * out_of_window sejak awal, tapi dulu jalur cron (attendance:sync-access-logs) menghitungnya lalu
 * membuang, tidak pernah tersimpan ke attendance_records. Test ini memastikan keduanya benar-benar
 * tersimpan lewat AttendanceRecordRepository::saveCalculatedResult().
 */
class FlexibleScheduleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_meets_target_and_out_of_window_are_persisted_for_flexible_schedule(): void
    {
        $company = Companies::create([
            'code' => 'CPA', 'name' => 'Company A', 'location' => 'Test', 'is_active' => true,
        ]);

        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP001']);

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Flexible Test',
            'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00',
            'flexible_checkin_until' => '10:00:00',
        ]);

        $date = Carbon::parse('2026-08-03'); // Senin
        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $date->dayOfWeek,
            'min_work_minutes' => 480,
            'max_work_minutes' => 480,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $date->copy()->subMonth()->toDateString(),
        ]);

        // Check-in 05:00 (di luar jendela 06:00-10:00 -> out_of_window=true), check-out 09:00
        // (cuma 4 jam = 240 menit, kurang dari min_work_minutes 480 -> meets_target=false).
        foreach (['05:00:00', '09:00:00'] as $time) {
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

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date->toDateString())->firstOrFail();

        $this->assertFalse($record->meets_target);
        $this->assertTrue($record->out_of_window);
    }
}
