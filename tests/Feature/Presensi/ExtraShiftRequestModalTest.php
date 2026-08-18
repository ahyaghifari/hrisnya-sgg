<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\ExtraShiftRequestModal;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Modal "Extra Shift" instan di widget Jadwal Shift Saya (Dashboard) - buat atasan berjadwal
 * shift (mis. Kepala Ruangan) assign extra shift ke bawahannya sendiri, auto approved
 * (source=assigned), scope karyawannya pakai ResolvesShiftTeamMembers (bawahan struktur jabatan
 * + siapa pun yang pakai Schedule sama) + diri sendiri - BUKAN company-wide seperti
 * ExtraShiftPage (halaman admin).
 */
class ExtraShiftRequestModalTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: User, 4: User, 5: User, 6: ScheduleShiftType} kepala, bawahan1 (roster di-cover), bawahan2 (dapat extra shift), bawahan3 (tanpa jadwal shift), orientasi (bukan bawahan formal, schedule sama), outsider (unit lain, schedule beda) */
    private function makeScenario(): array
    {
        Permission::firstOrCreate(['name' => 'Create:ExtraShiftAssignment', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepala Ruangan Test', 'guard_name' => 'web']);
        $role->givePermissionTo('Create:ExtraShiftAssignment');

        $company = Companies::create(['code' => 'ESM', 'name' => 'RS Extra Shift Modal Test', 'location' => 'Test', 'is_active' => true]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat Unit A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        // Schedule unit LAIN - dipakai outsider, harus tetap tidak ikut kehitung.
        $otherSchedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat Unit B', 'schedule_type' => 'shift']);
        $pagiUnitB = ScheduleShiftType::create(['schedule_id' => $otherSchedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        $kepalaPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 3, 'parent_id' => $kepalaPosition->id]);

        $kepala = User::factory()->create(['company_id' => $company->id]);
        $kepala->assignRole($role);
        $this->assignJobPosition($kepala, $kepalaPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $kepala->id, 'shift_type_id' => $pagi->id, 'date' => now()->addDays(2)->toDateString(), 'off_day' => false]);

        $bawahan1 = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Satu']);
        $this->assignJobPosition($bawahan1, $staffPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $bawahan1->id, 'shift_type_id' => $pagi->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        $bawahan2 = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Dua']);
        $this->assignJobPosition($bawahan2, $staffPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $bawahan2->id, 'shift_type_id' => $pagi->id, 'date' => now()->addDays(3)->toDateString(), 'off_day' => false]);

        // Bawahan yang belum punya jadwal shift sama sekali - tidak boleh muncul di pilihan.
        $bawahan3 = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Tiga Tanpa Jadwal']);
        $this->assignJobPosition($bawahan3, $staffPosition);

        // Kasus nyata (NIP 02022026834): "Perawat Orientasi" - jabatan generik, BUKAN anak
        // struktur Kepala Ruangan ini, tapi roster shift-nya nunjuk ke Schedule unit yang sama
        // dengan bawahan1/bawahan2 - harus tetap muncul di pilihan.
        $orientasi = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Orientasi Unit A']);
        $orientasiPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat Orientasi', 'planned_manpower' => 5]);
        $this->assignJobPosition($orientasi, $orientasiPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $orientasi->id, 'shift_type_id' => $pagi->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        // Karyawan lain di company yang sama, unit LAIN (schedule beda) - tidak boleh ikut
        // muncul di pilihan (scope-nya per tim/schedule, bukan company-wide).
        $outsider = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Unit Lain']);
        $otherKepalaPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan Lain', 'planned_manpower' => 1]);
        $this->assignJobPosition($outsider, $otherKepalaPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $outsider->id, 'shift_type_id' => $pagiUnitB->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        return [$kepala, $bawahan1, $bawahan2, $bawahan3, $orientasi, $outsider, $pagi];
    }

    public function test_eligible_employees_scoped_to_team_including_shared_schedule(): void
    {
        [$kepala, $bawahan1, $bawahan2, $bawahan3, $orientasi, $outsider] = $this->makeScenario();

        $component = Livewire::actingAs($kepala)->test(ExtraShiftRequestModal::class);

        $names = $component->get('eligibleEmployees')->pluck('name')->all();

        $this->assertContains($kepala->name, $names);
        $this->assertContains($bawahan1->name, $names);
        $this->assertContains($bawahan2->name, $names);
        $this->assertContains($orientasi->name, $names); // Bukan bawahan formal, tapi schedule sama.
        $this->assertNotContains($bawahan3->name, $names); // Belum punya jadwal shift.
        $this->assertNotContains($outsider->name, $names); // Schedule beda (unit lain).
    }

    public function test_submit_creates_approved_extra_shift_assignment(): void
    {
        [$kepala, $bawahan1, $bawahan2] = $this->makeScenario();

        $roster = ScheduleShiftAssignment::where('user_id', $bawahan1->id)->first();

        Livewire::actingAs($kepala)->test(ExtraShiftRequestModal::class)
            ->call('openModal')
            ->set('user_id', $bawahan2->id)
            ->set('covering_user_id', $bawahan1->id)
            ->set('roster_id', $roster->id)
            ->call('submit');

        $this->assertDatabaseHas('extra_shift_assignments', [
            'user_id' => $bawahan2->id,
            'covering_user_id' => $bawahan1->id,
            'shift_type_id' => $roster->shift_type_id,
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $kepala->id,
        ]);
    }

    public function test_forbidden_without_create_permission(): void
    {
        [, $bawahan1] = $this->makeScenario();
        $noPermUser = User::factory()->create(['company_id' => $bawahan1->company_id]);

        Livewire::actingAs($noPermUser)->test(ExtraShiftRequestModal::class)
            ->call('openModal')
            ->assertForbidden();

        $this->assertDatabaseCount('extra_shift_assignments', 0);
    }
}
