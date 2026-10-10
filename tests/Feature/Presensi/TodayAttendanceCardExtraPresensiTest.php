<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Dashboard;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kartu "Presensi Hari Ini": kalau hari ini ada Extra/Double Shift, di kanan jam masuk/keluar
 * presensi normal muncul blok "Presensi Extra" (masuk di atas, keluar di bawah) supaya karyawan
 * melihat sendiri jam extra shift-nya, lengkap dengan ikon petir.
 */
class TodayAttendanceCardExtraPresensiTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDay(?string $category): User
    {
        $company = Companies::create(['code' => 'EXC', 'name' => 'Extra Card Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => 'EXC001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $make = fn (string $code, string $start, string $end) => ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => $code, 'shift_type' => strtolower($code),
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => false,
            'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $pagi = $make('PAGI', '08:00:00', '14:00:00');
        $siang = $make('SIANG', '14:00:00', '20:00:00');

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => today(), 'off_day' => false]);
        AttendanceRecords::create([
            'user_id' => $user->id, 'nip' => $user->nip, 'date' => today()->toDateString(),
            'check_in_time' => '07:14:00', 'check_out_time' => '14:00:00', 'status' => 'present',
        ]);

        if ($category) {
            $extra = ExtraShiftAssignment::create([
                'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => today(),
                'status' => 'approved', 'source' => $category === 'double_shift' ? 'swap_conflict' : 'assigned',
                'category' => $category, 'created_by' => $user->id,
            ]);
            ExtraShiftAttendanceRecord::create([
                'extra_shift_assignment_id' => $extra->id, 'check_in_time' => '14:00:00', 'check_out_time' => '20:38:00',
                'status' => 'present', 'source' => 'access_logs_shift',
            ]);
        }

        return $user;
    }

    public static function categories(): array
    {
        return ['extra shift' => ['extra_shift'], 'double shift' => ['double_shift']];
    }

    #[DataProvider('categories')]
    public function test_extra_presensi_block_shows_extra_in_and_out_times(string $category): void
    {
        $user = $this->setUpDay($category);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Presensi Extra')
            ->assertSee('07:14')
            ->assertSee('14:00')
            ->assertSee('20:38')
            ->assertSeeHtml('extra-bolt');
    }

    public function test_extra_presensi_block_is_hidden_without_extra_shift(): void
    {
        $user = $this->setUpDay(null);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('07:14')
            ->assertDontSee('Presensi Extra')
            ->assertDontSeeHtml('extra-bolt');
    }
}
