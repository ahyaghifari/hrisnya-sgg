<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use App\Services\Attendance\AttendanceResyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Hitung Ulang Presensi" di Profil Saya (AttendanceResyncService::resyncUserRange) untuk jadwal
 * Fixed: masuk 08:17 dan pulang 16:45 harus tersimpan lengkap di semua hari dalam rentang.
 */
class ProfileResyncFixedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function setUpFixed(): void
    {
        $company = Companies::create(['code' => 'PRF', 'name' => 'PRF Co', 'location' => 'Test', 'is_active' => true]);
        $this->user = User::factory()->create(['nip' => 'PRF001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Kantor', 'schedule_type' => 'fixed']);

        foreach ([1, 2, 3, 4, 5] as $dow) {
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id, 'day_of_week' => $dow, 'is_off_day' => false, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'break_minutes' => 60, 'min_work_minutes' => 0, 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
                'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
            ]);
        }

        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $this->user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);
        AttendanceDevice::create(['company_id' => $company->id, 'name' => 'Lobby', 'is_active' => true]);
    }

    private function scan(string $date, string $time, ?string $device = 'Lobby'): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $this->user->nip, 'access_datetime' => "$date $time:00", 'access_date' => $date, 'access_time' => $time . ':00',
            'first_name' => $this->user->name, 'last_name' => '', 'person_name' => $this->user->name, 'person_group' => 'default',
            'device_name' => $device,
        ]);
    }

    public function test_every_working_day_keeps_check_in_and_check_out(): void
    {
        $this->setUpFixed();

        // Senin-Jumat 2026-08-03..07, masuk 08:17 pulang 16:45 setiap hari.
        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'] as $date) {
            $this->scan($date, '08:17');
            $this->scan($date, '16:45');
        }

        $counts = app(AttendanceResyncService::class)->resyncUserRange($this->user, '2026-08-03', '2026-08-07');

        $rows = AttendanceRecords::where('nip', 'PRF001')->orderBy('date')->get();
        $this->assertCount(5, $rows);

        foreach ($rows as $row) {
            $this->assertSame('08:17:00', $row->check_in_time, "masuk {$row->date}");
            $this->assertSame('16:45:00', $row->check_out_time, "pulang {$row->date}");
            $this->assertSame('late', $row->status);
            $this->assertSame(2, (int) $row->late_minutes);
            $this->assertSame(448, (int) $row->working_minutes);
        }

        $this->assertSame(5, $counts['logs']);
    }

    public function test_resync_of_already_saved_rows_does_not_wipe_check_in(): void
    {
        $this->setUpFixed();
        $this->scan('2026-08-03', '08:17');
        $this->scan('2026-08-03', '16:45');

        $service = app(AttendanceResyncService::class);
        $service->resyncUserRange($this->user, '2026-08-03', '2026-08-03');
        $service->resyncUserRange($this->user, '2026-08-03', '2026-08-03');

        $row = AttendanceRecords::where('nip', 'PRF001')->where('date', '2026-08-03')->first();
        $this->assertSame('08:17:00', $row->check_in_time);
        $this->assertSame('16:45:00', $row->check_out_time);
    }
}
