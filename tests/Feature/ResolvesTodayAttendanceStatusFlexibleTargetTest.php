<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kartu "Presensi Hari Ini" (beranda) - target jam pulang jadwal Flexible harus ikut menambahkan
 * break_minutes, bukan cuma min_work_minutes. FlexibleScheduleCalculator MENGURANGI waktu
 * istirahat dari working_minutes yang beneran dihitung (dikonfirmasi user 2026-08-05, tetap
 * dipertahankan) - jadi target checkout yang ditampilkan harus konsisten: karyawan butuh hadir
 * lebih lama dari sekadar min_work_minutes buat menutupi jam istirahatnya juga.
 */
class ResolvesTodayAttendanceStatusFlexibleTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_flexible_target_checkout_includes_break_minutes(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $today = Carbon::today();

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Flexible Test',
            'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00',
            'flexible_checkin_until' => '10:00:00',
        ]);

        // Target = max_work_minutes 480 (8 jam kerja) + 60 menit istirahat; min 300 cuma pagar
        // pulang duluan dan tidak boleh ikut menentukan jam pulang target di kartu.
        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $today->dayOfWeek,
            'min_work_minutes' => 300,
            'max_work_minutes' => 480,
            'break_minutes' => 60,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $today->copy()->subMonth()->toDateString(),
        ]);

        AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $today->toDateString(),
            'check_in_time' => '08:00:00',
            'status' => 'present',
        ]);

        $this->actingAs($user);

        // Property computed (getTodayAttendanceStatusProperty) - todayAttendanceStatus() sendiri
        // protected di trait, tidak bisa dipanggil langsung dari luar kelas.
        $status = Livewire::test(Dashboard::class)->instance()->todayAttendanceStatus;

        // 08:00 + 480 menit (8 jam) + 60 menit istirahat = 17:00, BUKAN 16:00.
        $this->assertSame('17:00', $status['flexible_target_checkout']);
    }
}
