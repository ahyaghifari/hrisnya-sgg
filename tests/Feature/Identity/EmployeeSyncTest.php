<?php

namespace Tests\Feature\Identity;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Person\Person;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * employees / employee_careers = salinan satu arah dari users + user_profiles +
 * user_job_positions (EmployeeSyncService, dipicu EmployeeSyncObserver). Tabel lama tetap sumber.
 */
class EmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'EMP', 'name' => 'Company Emp', 'location' => 'Test', 'is_active' => true]);
    }

    private function position(string $name): JobPositions
    {
        return JobPositions::create(['company_id' => $this->company->id, 'name' => $name, 'planned_manpower' => 1]);
    }

    public function test_profile_and_active_job_position_are_copied_to_employee_and_career(): void
    {
        $pangkat = Pangkats::create(['name' => 'P1', 'company_id' => $this->company->id]);
        $kelas = Kelas::create(['name' => 'K1']);
        $type = EmployeeTypes::create(['name' => 'Tetap']);
        $jabatan = $this->position('Perawat');

        $user = User::factory()->create(['company_id' => $this->company->id, 'nip' => 'NIP001']);
        $user->profile()->create([
            'employee_type_id' => $type->id, 'job_position_id' => $jabatan->id,
            'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id,
            'joining_date' => '2024-01-15', 'tmt_tetap' => '2024-07-15',
        ]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-15', 'status' => 'active']);

        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('NIP001', $employee->nip);
        $this->assertSame($this->company->id, $employee->company_id);
        $this->assertSame($jabatan->id, $employee->job_position_id);
        $this->assertSame($pangkat->id, $employee->pangkat_id);
        $this->assertSame('2024-01-15', $employee->joining_date->toDateString());
        $this->assertSame('aktif', $employee->status);
        $this->assertSame(Person::where('user_id', $user->id)->value('id'), $employee->person_id);

        $career = $employee->careers->sole();
        $this->assertSame($jabatan->id, $career->job_position_id);
        $this->assertSame($pangkat->id, $career->pangkat_id, 'Baris aktif memuat snapshot pangkat/kelas.');
        $this->assertSame($kelas->id, $career->kelas_id);
    }

    public function test_editing_profile_updates_employee(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $profile = $user->profile()->create([]);
        $pangkat = Pangkats::create(['name' => 'P2', 'company_id' => $this->company->id]);

        $profile->update(['pangkat_id' => $pangkat->id, 'joining_date' => '2023-03-01']);

        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($pangkat->id, $employee->pangkat_id);
        $this->assertSame('2023-03-01', $employee->joining_date->toDateString());
        $this->assertSame(1, Employee::where('user_id', $user->id)->count());
    }

    public function test_job_change_keeps_history_and_old_row_has_no_pangkat_snapshot(): void
    {
        $pangkat = Pangkats::create(['name' => 'P1', 'company_id' => $this->company->id]);
        $lama = $this->position('Perawat');
        $baru = $this->position('Kepala Ruangan');

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->profile()->create(['job_position_id' => $lama->id, 'pangkat_id' => $pangkat->id]);
        $ledger = UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $lama->id, 'start_date' => '2024-01-01', 'status' => 'active']);

        // Mutasi: tutup baris lama, buka baris baru, profil ikut pindah.
        $ledger->update(['end_date' => '2025-01-01', 'status' => 'inactive']);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $baru->id, 'start_date' => '2025-01-01', 'status' => 'active']);
        $user->profile->update(['job_position_id' => $baru->id]);

        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($baru->id, $employee->job_position_id);
        $this->assertCount(2, $employee->careers);

        $old = $employee->careers->firstWhere('job_position_id', $lama->id);
        $this->assertSame('inactive', $old->status);
        $this->assertSame('2025-01-01', $old->end_date->toDateString());
        $this->assertSame($pangkat->id, $old->pangkat_id, 'Snapshot yang sudah tersimpan tidak ditimpa/dihapus saat baris ditutup.');
    }

    public function test_deleting_ledger_row_removes_its_career_row(): void
    {
        $jabatan = $this->position('Perawat');
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->profile()->create([]);
        $ledger = UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-01', 'status' => 'active']);
        $this->assertSame(1, EmployeeCareer::count());

        $ledger->delete();

        $this->assertSame(0, EmployeeCareer::count());
    }

    public function test_soft_deleting_user_marks_employee_nonaktif(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $user->delete();

        $this->assertSame('nonaktif', Employee::where('user_id', $user->id)->value('status'));
    }

    public function test_super_admin_accounts_get_person_but_no_employee(): void
    {
        $user = User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0']); // NON_KARYAWAN, bukan payroll-eligible

        $this->assertSame(1, Person::where('user_id', $user->id)->count());
        $this->assertSame(0, Employee::where('user_id', $user->id)->count());
    }

    public function test_backfill_employees_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $jabatan = $this->position('Perawat');
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->profile()->create(['job_position_id' => $jabatan->id]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-01', 'status' => 'active']);
        EmployeeCareer::query()->delete();
        Employee::query()->delete();

        $this->artisan('identity:backfill-employees', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Employee::count());

        $this->artisan('identity:backfill-employees')->assertSuccessful();
        $this->artisan('identity:backfill-employees')->assertSuccessful();

        $this->assertSame(1, Employee::where('user_id', $user->id)->count());
        $this->assertSame(1, EmployeeCareer::count());
    }
}
