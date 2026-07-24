<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\SchedulesPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tombol "Hapus" di daftar Jadwal — cuma tampil/boleh dieksekusi kalau jadwal itu belum ada
 * jabatan yang di-assign (schedule_assignments), dipakai sebagai proxy "sudah kepakai" karena
 * jadwal baru ini belum tersambung ke attendance_records sama sekali (lihat komentar di
 * SchedulesPage::hasAttendanceHistory()).
 */
class SchedulesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Schedules Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Create:Schedule', 'Delete:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_deletes_schedule_without_assignments(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kosong', 'schedule_type' => ScheduleType::FIXED->value]);

        Livewire::actingAs($user)
            ->test(SchedulesPage::class)
            ->set('companyId', $company->id)
            ->call('deleteSchedule', $schedule->id);

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_cannot_delete_schedule_with_job_position_assigned(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Terpakai', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'is_draft' => false]);

        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $job->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(SchedulesPage::class)
            ->set('companyId', $company->id)
            ->call('deleteSchedule', $schedule->id)
            ->assertForbidden();

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }
}
