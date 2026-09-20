<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\SchedulesPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftType;
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
        $literals = [CustomPermissionEnum::SISTEM_ABSENSI->value, 'ViewAny:Schedule', 'Create:Schedule', 'Update:Schedule', 'Delete:Schedule', 'Duplicate:Schedule'];
        foreach ($literals as $literal) {
            Permission::firstOrCreate(['name' => $literal, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Absensi Schedules Test', 'guard_name' => 'web']);
        $role->givePermissionTo($literals);

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

    public function test_duplicates_schedule_rules_only_not_members(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $head = User::factory()->create(['company_id' => $company->id]);
        $job = JobPositions::create(['name' => 'Perawat', 'company_id' => $company->id, 'status' => 'approved']);

        $source = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Shift Ruang A',
            'schedule_type' => ScheduleType::SHIFT->value,
            'head_user_id' => $head->id,
        ]);
        foreach ([['P', 'Pagi', 'pagi', '07:00', '14:00'], ['S', 'Siang', 'siang', '14:00', '21:00']] as [$code, $name, $cat, $start, $end]) {
            ScheduleShiftType::create([
                'schedule_id' => $source->id,
                'code' => $code, 'name' => $name, 'shift_type' => $cat,
                'start_time' => $start, 'end_time' => $end,
            ]);
        }
        ScheduleAssignment::create([
            'company_id' => $company->id,
            'job_position_id' => $job->id,
            'schedule_id' => $source->id,
            'effective_from' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(SchedulesPage::class)
            ->set('companyId', $company->id)
            ->call('duplicateSchedule', $source->id)
            ->assertHasNoErrors();

        $copy = Schedule::where('name', 'Shift Ruang A (Salinan)')->first();

        $this->assertNotNull($copy);
        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame($company->id, $copy->company_id);
        $this->assertNull($copy->head_user_id, 'Kepala Ruangan tidak ikut diduplikat');
        $this->assertEqualsCanonicalizing(['P', 'S'], $copy->shiftTypes->pluck('code')->all());
        $this->assertSame(0, ScheduleAssignment::where('schedule_id', $copy->id)->count(), 'penempatan jabatan tidak ikut');
        // Sumber tidak tersentuh.
        $this->assertSame(2, $source->shiftTypes()->count());
        $this->assertSame(1, ScheduleAssignment::where('schedule_id', $source->id)->count());
    }

    public function test_cannot_delete_schedule_with_job_position_assigned(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Terpakai', 'schedule_type' => ScheduleType::FIXED->value]);
        $job = JobPositions::create(['name' => 'Staff IT', 'company_id' => $company->id, 'status' => 'approved']);

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

    /** Duplikat sekarang permission sendiri (Duplicate:Schedule) - Create:Schedule TIDAK cukup
     * lagi (instruksi user 2026-09-16, lihat migration seed_duplicate_schedule_permission). */
    public function test_cannot_duplicate_schedule_without_duplicate_permission(): void
    {
        $literals = [CustomPermissionEnum::SISTEM_ABSENSI->value, 'ViewAny:Schedule', 'Create:Schedule'];
        foreach ($literals as $literal) {
            Permission::firstOrCreate(['name' => $literal, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Create Only Schedules Test', 'guard_name' => 'web']);
        $role->givePermissionTo($literals);
        $user = User::factory()->create();
        $user->assignRole($role);

        $company = $this->makeCompany();
        $source = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Sumber', 'schedule_type' => ScheduleType::FIXED->value]);

        Livewire::actingAs($user)
            ->test(SchedulesPage::class)
            ->set('companyId', $company->id)
            ->call('duplicateSchedule', $source->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('schedules', ['name' => 'Jadwal Sumber (Salinan)']);
    }
}
