<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * recalculateWorkingHours() - tombol TERPISAH dari syncPeriodAttendance(): hitung ulang
 * working_minutes + late_minutes/early_leave_minutes/status pakai rumus jadwal ASLI tiap
 * karyawan (Fixed/Flexible, istirahat DIPOTONG sesuai config jadwal masing-masing). Sebelumnya
 * (2026-08-05) status sengaja tidak disentuh tombol ini - digabung lagi 2026-08-12.
 */
class RekapPresensiPageRecalculateWorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SYNC_DATA_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Recalculate Working Hours Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::SYNC_DATA_ABSENSI->value]);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_recalculates_fixed_schedule_subtracting_break_minutes(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $date = Carbon::parse('2026-07-06'); // Senin

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Fixed Test',
            'schedule_type' => 'fixed',
        ]);

        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 60,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $date->copy()->subMonth()->toDateString(),
        ]);

        // Nilai lama sengaja durasi mentah (480) - harus ditimpa jadi 420 (dipotong istirahat 60).
        $record = AttendanceRecords::create([
            'user_id' => $employee->id, 'nip' => $employee->nip, 'date' => $date->toDateString(),
            'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00', 'working_minutes' => 480, 'status' => 'present',
        ]);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('recalculateWorkingHours');

        $this->assertSame(420, $record->refresh()->working_minutes);
        $this->assertSame('present', $record->status); // status tidak ikut berubah
    }

    public function test_recalculates_flexible_schedule_subtracting_break_minutes(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $date = Carbon::parse('2026-07-06');

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Flexible Test',
            'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00',
            'flexible_checkin_until' => '10:00:00',
        ]);

        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $date->dayOfWeek,
            'break_minutes' => 60,
        ]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'effective_from' => $date->copy()->subMonth()->toDateString(),
        ]);

        // 08:00 - 17:00 = 540 menit mentah, dipotong istirahat 60 -> 480.
        $record = AttendanceRecords::create([
            'user_id' => $employee->id, 'nip' => $employee->nip, 'date' => $date->toDateString(),
            'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'working_minutes' => 540, 'status' => 'present',
        ]);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('recalculateWorkingHours');

        $this->assertSame(480, $record->refresh()->working_minutes);
    }

    public function test_skips_employee_without_any_schedule(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);
        // Sengaja tidak bikin Schedule/ScheduleAssignment sama sekali.

        $record = AttendanceRecords::create([
            'user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-06',
            'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00', 'working_minutes' => 480, 'status' => 'present',
        ]);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('recalculateWorkingHours');

        // Tidak ada jadwal ketemu -> dilewati, nilai lama tetap.
        $this->assertSame(480, $record->refresh()->working_minutes);
    }

    public function test_user_without_sync_permission_cannot_run_it(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Only Recalc Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->assignRole($role);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('recalculateWorkingHours')
            ->assertForbidden();
    }
}
