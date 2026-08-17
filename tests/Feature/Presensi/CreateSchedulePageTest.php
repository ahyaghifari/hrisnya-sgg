<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\CreateSchedulePage;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CreateSchedulePage dipakai dobel: Create (`$schedule` null) dan Edit
 * (`/presensi/jadwal/{schedule}/edit`) — lihat komentar kelas.
 */
class CreateSchedulePageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Create Schedule Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Create:Schedule', 'Update:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_creates_new_schedule_with_fixed_days(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::FIXED->value)
            ->call('nextStep')
            ->set('name', 'Jadwal Baru')
            ->call('save');

        $this->assertDatabaseHas('schedules', ['name' => 'Jadwal Baru', 'company_id' => $company->id]);

        $schedule = Schedule::where('name', 'Jadwal Baru')->first();
        $this->assertSame(7, $schedule->fixedDays()->count());
    }

    public function test_edit_mode_preloads_existing_data_and_locks_type(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:30', 'end_time' => '16:30', 'break_minutes' => 60]);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 0, 'is_off_day' => true]);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class, ['schedule' => $schedule])
            ->assertSet('step', 2)
            ->assertSet('name', 'Jam Kerja Normal')
            ->assertSet('scheduleType', ScheduleType::FIXED->value)
            ->assertSet('days.1.start_time', '08:30')
            ->assertDontSee('Ganti tipe jadwal');
    }

    public function test_edit_mode_updates_existing_schedule_and_day_rows(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:30', 'end_time' => '16:30', 'break_minutes' => 60]);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class, ['schedule' => $schedule])
            ->set('name', 'Jam Kerja Baru')
            ->set('days.1.start_time', '09:00')
            ->call('save');

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'name' => 'Jam Kerja Baru']);

        // Update baris hari lama (bukan bikin baris baru) — masih 1 baris day_of_week=1 buat schedule ini.
        $this->assertSame(1, ScheduleFixedDay::where('schedule_id', $schedule->id)->where('day_of_week', 1)->count());
        $this->assertDatabaseHas('schedule_fixed_days', ['schedule_id' => $schedule->id, 'day_of_week' => 1, 'start_time' => '09:00']);
    }

    public function test_edit_creates_change_log_snapshotting_old_state(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jam Kerja Normal', 'schedule_type' => ScheduleType::FIXED->value]);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_off_day' => false, 'start_time' => '08:30', 'end_time' => '16:30', 'break_minutes' => 60]);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class, ['schedule' => $schedule])
            ->set('name', 'Jam Kerja Baru')
            ->set('days.1.start_time', '09:00')
            ->call('save');

        $this->assertDatabaseHas('schedule_change_logs', [
            'schedule_id' => $schedule->id,
            'changed_by' => $user->id,
        ]);

        $log = \App\Models\Shift\ScheduleChangeLog::where('schedule_id', $schedule->id)->first();
        $this->assertSame('Jam Kerja Normal', $log->snapshot['name']); // nama LAMA, bukan yang baru
        $this->assertSame('08:30', $log->snapshot['days'][1]['start_time']); // jam LAMA, bukan 09:00
    }

    public function test_create_does_not_generate_change_log(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::FIXED->value)
            ->call('nextStep')
            ->set('name', 'Jadwal Baru')
            ->call('save');

        $this->assertDatabaseCount('schedule_change_logs', 0);
    }

    public function test_shift_type_add_remove_rows_and_lists_company_employees(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Siti Aminah']);
        User::factory()->create(); // beda perusahaan, tidak boleh ikut kelist

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::SHIFT->value)
            ->call('nextStep')
            ->assertCount('shiftTypes', 1) // 1 baris otomatis disediakan begitu tipe Shift dipilih
            ->call('addShiftType')
            ->call('addShiftType')
            ->assertCount('shiftTypes', 3)
            ->call('removeShiftType', 0)
            ->assertCount('shiftTypes', 2)
            ->call('removeShiftType', 0)
            ->call('removeShiftType', 0)
            ->assertCount('shiftTypes', 1) // tidak bisa turun di bawah 1 baris
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Aminah');

        $this->assertSame(2, User::where('company_id', $company->id)->count());
    }

    public function test_shift_type_rows_are_persisted_on_save(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::SHIFT->value)
            ->call('nextStep')
            ->set('name', 'Jadwal Shift Pabrik')
            ->set('shiftTypes.0.code', 'P')
            ->set('shiftTypes.0.name', 'Shift Pagi')
            ->call('save');

        $schedule = Schedule::where('name', 'Jadwal Shift Pabrik')->first();
        $this->assertNotNull($schedule);
        $this->assertDatabaseHas('schedule_shift_types', [
            'schedule_id' => $schedule->id,
            'code' => 'P',
            'name' => 'Shift Pagi',
        ]);
    }

    public function test_shift_type_row_removed_in_edit_mode_is_deleted_on_save(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Shift', 'schedule_type' => ScheduleType::SHIFT->value]);
        ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'M', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00']);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class, ['schedule' => $schedule])
            ->assertCount('shiftTypes', 2)
            ->call('removeShiftType', 1)
            ->set('shiftPattern', \App\Enum\ShiftPatternEnum::TWO_SHIFT->value)
            ->call('save');

        $this->assertSame(1, ScheduleShiftType::where('schedule_id', $schedule->id)->count());
        $this->assertDatabaseHas('schedule_shift_types', ['schedule_id' => $schedule->id, 'code' => 'P']);
        $this->assertSoftDeleted('schedule_shift_types', ['schedule_id' => $schedule->id, 'code' => 'M']);
    }

    public function test_shift_save_persists_selected_employees_and_redirects_to_show_only_on_first_create(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $emp1 = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        $emp2 = User::factory()->create(['company_id' => $company->id, 'name' => 'Siti Aminah']);
        $emp3 = User::factory()->create(['company_id' => $company->id, 'name' => 'Andi Wijaya']);

        $component = Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::SHIFT->value)
            ->call('nextStep')
            ->set('name', 'Jadwal Shift Karyawan')
            ->set('selectedEmployeeIds', [$emp1->id, $emp2->id])
            ->call('save');

        $schedule = Schedule::where('name', 'Jadwal Shift Karyawan')->first();
        $this->assertNotNull($schedule);

        // Baru dibuat -> langsung pindah ke halaman Lihat Jadwal.
        $component->assertRedirect(route('presensi.jadwal.show', $schedule));

        $this->assertDatabaseHas('schedule_assignments', ['schedule_id' => $schedule->id, 'user_id' => $emp1->id]);
        $this->assertDatabaseHas('schedule_assignments', ['schedule_id' => $schedule->id, 'user_id' => $emp2->id]);
        $this->assertDatabaseMissing('schedule_assignments', ['schedule_id' => $schedule->id, 'user_id' => $emp3->id]);

        // Save kedua (sudah dalam mode Edit) meng-update pilihan, bukan bikin schedule baru,
        // dan TIDAK redirect lagi (beda dari save pertama).
        $component->set('selectedEmployeeIds', [$emp2->id, $emp3->id])->call('save')->assertNoRedirect();

        $this->assertSame(1, Schedule::where('name', 'Jadwal Shift Karyawan')->count());
        $this->assertDatabaseMissing('schedule_assignments', ['schedule_id' => $schedule->id, 'user_id' => $emp1->id]);
        $this->assertDatabaseHas('schedule_assignments', ['schedule_id' => $schedule->id, 'user_id' => $emp3->id]);
    }

    public function test_shift_employee_table_shows_jabatan_and_unit(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $org = \App\Models\Master\OrgStructure::create(['company_id' => $company->id, 'name' => 'Unit Produksi']);
        $job = \App\Models\Master\JobPositions::create(['name' => 'Operator', 'company_id' => $company->id, 'is_draft' => false, 'org_structure_id' => $org->id]);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        \App\Models\User\UserJobPositions::create(['user_id' => $employee->id, 'job_position_id' => $job->id, 'status' => 'active', 'start_date' => now()]);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::SHIFT->value)
            ->call('nextStep')
            ->assertSee('Budi Santoso')
            ->assertSee('Operator')
            ->assertSee('Unit Produksi');
    }

    public function test_changing_company_clears_selected_employees(): void
    {
        $user = $this->actingAsAbsensi();
        $company = $this->makeCompany();
        $otherCompany = Companies::create(['code' => 'OTH', 'name' => 'Other Co', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(CreateSchedulePage::class)
            ->set('companyId', $company->id)
            ->call('selectType', ScheduleType::SHIFT->value)
            ->call('nextStep')
            ->set('selectedEmployeeIds', [$employee->id])
            ->assertSet('selectedEmployeeIds', [$employee->id])
            ->set('companyId', $otherCompany->id)
            ->assertSet('selectedEmployeeIds', []);
    }
}
