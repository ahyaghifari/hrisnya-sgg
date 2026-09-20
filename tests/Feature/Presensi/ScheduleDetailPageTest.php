<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleDetailPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman "Lihat" jadwal (read-only) — tab Jam Kerja/Kantor/Riwayat, niru fingerspot.io
 * (knowledge/absensi). Riwayat baca `schedule_change_logs` yang diisi CreateSchedulePage
 * jalur edit.
 */
class ScheduleDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Schedule Detail Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleDetailPage::class, ['schedule' => $schedule])
            ->assertForbidden();
    }

    public function test_shows_fixed_day_table_and_off_day_strip(): void
    {
        $user = $this->actingAsAbsensi();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);

        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:30', 'end_time' => '16:30', 'break_minutes' => 0]);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 0, 'is_off_day' => true]);

        Livewire::actingAs($user)
            ->test(ScheduleDetailPage::class, ['schedule' => $schedule])
            ->assertOk()
            ->assertSee('Jam Kerja Normal')
            ->assertSee('08:30')
            ->assertSee('Libur');
    }

    public function test_kantor_tab_lists_assigned_job_positions(): void
    {
        $user = $this->actingAsAbsensi();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'status' => 'approved']);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $job->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleDetailPage::class, ['schedule' => $schedule])
            ->call('setTab', 'kantor')
            ->assertSee('Staff IT')
            ->assertSee($company->name);
    }

    public function test_riwayat_tab_shows_empty_state(): void
    {
        $user = $this->actingAsAbsensi();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);

        Livewire::actingAs($user)
            ->test(ScheduleDetailPage::class, ['schedule' => $schedule])
            ->call('setTab', 'riwayat')
            ->assertSee('Belum ada riwayat perubahan');
    }

    public function test_riwayat_tab_lists_logs_and_popup_shows_old_snapshot(): void
    {
        $user = $this->actingAsAbsensi();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);

        $log = \App\Models\Shift\ScheduleChangeLog::create([
            'schedule_id' => $schedule->id,
            'changed_by' => $user->id,
            'snapshot' => [
                'name' => 'Jam Kerja Lama',
                'schedule_type' => 'fixed',
                'days' => [1 => ['is_off_day' => false, 'start_time' => '08:00', 'end_time' => '16:00']],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleDetailPage::class, ['schedule' => $schedule])
            ->call('setTab', 'riwayat')
            ->assertSee($user->name)
            ->call('openLog', $log->id)
            ->assertSee('Riwayat Perubahan Jadwal')
            ->assertSee('08:00');
    }
}
