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
 * Sel roster yang off_day-nya berasal dari Cuti/Izin disetujui otomatis (schedule_status
 * 'cuti'/'izin') tidak boleh ketimpa balik jadi shift kerja biasa lewat edit manual/Isi Cepat/
 * Import/Reset - instruksi user 2026-09-24. Lihat ScheduleShiftRosterTab::getLeaveCellsProperty()
 * dan AttendaceReqService::markRosterDayOff().
 */
class ScheduleShiftRosterLeaveLockTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Roster Leave Lock Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Punya Update:Schedule ("Edit Jadwal") - satu-satunya yang boleh menimpa sel Cuti/Izin
     * disetujui otomatis (instruksi user 2026-09-24). */
    private function actingAsScheduleOwner(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Schedule Owner Leave Lock Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule', 'Update:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'LOCK', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
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

    private function markCuti(Schedule $schedule, User $employee, string $date): ScheduleShiftAssignment
    {
        return ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id,
            'user_id' => $employee->id,
            'date' => $date,
            'off_day' => true,
            'shift_type_id' => null,
            'schedule_status' => 'cuti',
            'notes' => 'Cuti atau Izin disetujui (pengajuan #test).',
        ]);
    }

    public function test_set_cell_refuses_to_overwrite_leave_cell(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $this->markCuti($schedule, $employee, $date);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertTrue((bool) $row->off_day);
        $this->assertSame('cuti', $row->schedule_status);
    }

    public function test_save_roster_skips_leave_cell_in_draft(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $this->markCuti($schedule, $employee, $date);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertTrue((bool) $row->off_day);
        $this->assertSame('cuti', $row->schedule_status);
    }

    public function test_reset_period_does_not_delete_leave_cell(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $this->markCuti($schedule, $employee, $date);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('resetPeriod');

        $this->assertNotNull(ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first());
    }

    public function test_schedule_owner_can_override_leave_cell_via_set_cell(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $this->markCuti($schedule, $employee, $date);

        Livewire::actingAs($this->actingAsScheduleOwner())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertSame($shiftType->id, $row->shift_type_id);
        $this->assertFalse((bool) $row->off_day);
    }

    public function test_schedule_owner_can_override_leave_cell_via_save_roster(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $this->markCuti($schedule, $employee, $date);

        Livewire::actingAs($this->actingAsScheduleOwner())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertSame($shiftType->id, $row->shift_type_id);
        $this->assertFalse((bool) $row->off_day);
    }

    public function test_export_marks_leave_cell_with_distinct_code(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4);
        $this->markCuti($schedule, $employee, $date->toDateString());

        $component = Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule]);

        $leaveCells = $component->instance()->leaveCells;
        $roster = $component->instance()->roster;

        $export = new \App\Exports\Presensi\ScheduleShiftRosterExport($schedule, collect([$employee]), [$date], $roster, 'label', [], 'row', $leaveCells);
        $rows = $export->array();

        // $rows[0]/[1] = 2 baris judul, $rows[2] = header (legenda + grid), $rows[3] = baris
        // data pertama (legenda "L" digabung grid baris karyawan pertama) - kolom 0-3 legenda,
        // 4 kosong (gap), 5 nama karyawan, 6 kode tanggal pertama (lihat ScheduleShiftRosterExport::array()).
        $this->assertSame('C', $rows[3][6]);
    }
}
