<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleAssignmentsPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Atur Pengguna Jadwal" — assign Schedule ke JobPositions lewat tree OrgStructure, niru
 * fingerspot.io (knowledge/absensi/ATUR JABATAN DARI JADWAL*.png). Menulis ke
 * `schedule_assignments` (target job_position_id).
 */
class ScheduleAssignmentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Schedule Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleAssignmentsPage::class, ['schedule' => $schedule])
            ->assertForbidden();
    }

    public function test_renders_org_tree_and_functional_jobs(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);

        $org = OrgStructure::create(['name' => 'HC-GA', 'company_id' => $company->id, 'level' => 1]);
        JobPositions::create(['name' => 'HC Manager', 'company_id' => $company->id, 'org_structure_id' => $org->id, 'is_draft' => false]);
        JobPositions::create(['name' => 'Intern - Kampus A', 'company_id' => $company->id, 'is_draft' => false]);

        Livewire::actingAs($user)
            ->test(ScheduleAssignmentsPage::class, ['schedule' => $schedule])
            ->assertOk()
            ->assertSee('HC-GA')
            ->assertSee('HC Manager')
            ->assertSee('Jabatan Fungsional')
            ->assertSee('Intern - Kampus A');
    }

    public function test_job_position_assigned_to_another_active_schedule_is_blocked(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $otherSchedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Lain', 'schedule_type' => ScheduleType::FIXED->value]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'is_draft' => false]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $job->id,
            'schedule_id' => $otherSchedule->id,
            'effective_from' => now()->subDay(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleAssignmentsPage::class, ['schedule' => $schedule]);

        $this->assertSame(['job' => 'Jadwal Lain'], ['job' => $component->get('blockedJobPositions')[$job->id]]);
        $component->assertSee('Jadwal Lain');
    }

    public function test_save_creates_assignments_for_checked_positions(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'is_draft' => false]);

        Livewire::actingAs($user)
            ->test(ScheduleAssignmentsPage::class, ['schedule' => $schedule])
            ->set('effectiveFrom', now()->toDateString())
            ->set('selectedJobPositionIds', [$job->id])
            ->call('save');

        $this->assertDatabaseHas('schedule_assignments', [
            'schedule_id' => $schedule->id,
            'job_position_id' => $job->id,
        ]);
    }

    public function test_unchecking_a_previously_assigned_position_removes_it(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'is_draft' => false]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $job->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleAssignmentsPage::class, ['schedule' => $schedule])
            ->set('selectedJobPositionIds', [])
            ->call('save');

        $this->assertDatabaseMissing('schedule_assignments', [
            'schedule_id' => $schedule->id,
            'job_position_id' => $job->id,
        ]);
    }
}
