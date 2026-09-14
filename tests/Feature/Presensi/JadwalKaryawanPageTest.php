<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\JadwalKaryawanPage;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab "Belum Ada Jadwal" - karyawan yang SAMA SEKALI tidak punya assignment jadwal (Fixed/
 * Flexible/Shift) - instruksi user 2026-09-14.
 */
class JadwalKaryawanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_without_any_schedule_assignment_appears_in_unassigned_list(): void
    {
        $company = Companies::create(['code' => 'JK1', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:Schedule', 'guard_name' => 'web']));

        $noSchedule = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Tanpa Jadwal']);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Fixed', 'schedule_type' => 'fixed']);
        $hasSchedule = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Sudah Ada Jadwal']);
        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $hasSchedule->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $component = Livewire::actingAs($viewer)->test(JadwalKaryawanPage::class)->set('companyId', $company->id);

        $unassigned = $component->get('unassignedEmployees');

        $this->assertTrue($unassigned->contains('id', $noSchedule->id));
        $this->assertFalse($unassigned->contains('id', $hasSchedule->id));
    }

    public function test_employee_covered_only_via_job_position_assignment_is_not_flagged(): void
    {
        $company = Companies::create(['code' => 'JK2', 'name' => 'Test Company 2', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:Schedule', 'guard_name' => 'web']));

        $jobPosition = \App\Models\Master\JobPositions::create([
            'company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1,
        ]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Jabatan', 'schedule_type' => 'flexible']);
        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $jobPosition->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $employee->id, 'job_position_id' => $jobPosition->id]);

        $component = Livewire::actingAs($viewer)->test(JadwalKaryawanPage::class)->set('companyId', $company->id);

        $this->assertFalse($component->get('unassignedEmployees')->contains('id', $employee->id));
    }

    public function test_view_calendar_for_switches_tab_and_selects_employee(): void
    {
        $company = Companies::create(['code' => 'JK3', 'name' => 'Test Company 3', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:Schedule', 'guard_name' => 'web']));

        $employee = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($viewer)
            ->test(JadwalKaryawanPage::class)
            ->set('companyId', $company->id)
            ->call('setView', 'belum-jadwal')
            ->assertSet('view', 'belum-jadwal')
            ->call('viewCalendarFor', $employee->id)
            ->assertSet('view', 'kalender')
            ->assertSet('userId', $employee->id);
    }
}
