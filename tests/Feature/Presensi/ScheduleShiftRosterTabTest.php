<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifikasi edit manual per-sel di tab Detail Penjadwalan (setCell) — CUMA boleh kalau
 * use_template=false. Kalau use_template=true, sel dikunci read-only, koreksi rencananya lewat
 * alur Pengajuan Tukar Shift (belum dibangun), bukan timpa bebas per sel lagi.
 */
class ScheduleShiftRosterTabTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Roster Manual Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Manual',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);

        return [$schedule, $shiftType, $employee];
    }

    public function test_generate_controls_are_hidden_but_manual_dropdown_still_renders(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertDontSee('Mulai Dari')
            ->assertSee('Budi Santoso')
            ->assertSee($shiftType->code);
    }

    public function test_manual_cell_edit_assigns_shift_without_any_template(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('shift_type_id', $shiftType->id)
                ->where('off_day', false)
                ->exists()
        );
    }

    public function test_manual_cell_edit_can_set_off_day(): void
    {
        [$schedule, , $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, 'off');

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->whereNull('shift_type_id')
                ->where('off_day', true)
                ->exists()
        );
    }

    public function test_manual_cell_edit_can_clear_back_to_empty(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id,
            'user_id' => $employee->id,
            'date' => $date,
            'shift_type_id' => $shiftType->id,
            'off_day' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, '');

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    public function test_manual_cell_edit_is_blocked_when_schedule_uses_template(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('Mulai Dari')
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    public function test_grid_is_locked_until_start_edit_is_clicked(): void
    {
        [$schedule, , $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('Mulai Isi Jadwal')
            ->assertSet('editMode', false)
            ->call('startEdit')
            ->assertSet('editMode', true)
            ->assertSee('Simpan')
            ->assertSee('Batal');
    }

    public function test_save_roster_persists_only_changed_cells_from_draft(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $dateA = now()->startOfMonth()->addDays(4)->toDateString();
        $dateB = now()->startOfMonth()->addDays(9)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$dateA}", $shiftType->id)
            ->set("draft.{$employee->id}.{$dateB}", 'off')
            ->call('saveRoster')
            ->assertSet('editMode', false);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateA)
                ->where('shift_type_id', $shiftType->id)->where('off_day', false)->exists()
        );
        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateB)
                ->whereNull('shift_type_id')->where('off_day', true)->exists()
        );
    }

    public function test_cancel_edit_discards_draft_changes(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('cancelEdit')
            ->assertSet('editMode', false);

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    public function test_start_edit_is_blocked_when_schedule_uses_template(): void
    {
        $company = Companies::create(['code' => 'TPL', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->assertSet('editMode', false);
    }
}
