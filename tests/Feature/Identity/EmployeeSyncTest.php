<?php

namespace Tests\Feature\Identity;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * employees (+ identitas, alamat, lampiran, riwayat karir) = salinan satu arah dari users +
 * user_profiles + user_job_positions + user_profile_documents (EmployeeSyncService, dipicu
 * EmployeeSyncObserver). Tidak ada entitas Person terpisah. Tabel lama tetap sumber.
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

    private function employeeOf(User $user): Employee
    {
        return Employee::where('user_id', $user->id)->firstOrFail();
    }

    public function test_profile_and_active_job_position_are_copied_to_employee_and_career(): void
    {
        $pangkat = Pangkats::create(['name' => 'P1', 'company_id' => $this->company->id]);
        $kelas = Kelas::create(['name' => 'K1']);
        $type = EmployeeTypes::create(['name' => 'Tetap']);
        $jabatan = $this->position('Perawat');

        $user = User::factory()->create(['company_id' => $this->company->id, 'nip' => 'NIP001', 'name' => 'Budi Santoso']);
        $user->profile()->create([
            'employee_type_id' => $type->id, 'job_position_id' => $jabatan->id,
            'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id,
            'joining_date' => '2024-01-15', 'tmt_tetap' => '2024-07-15', 'marital_status' => 'single',
        ]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-15', 'status' => 'active']);

        $employee = $this->employeeOf($user);

        $this->assertSame('NIP001', $employee->nip);
        $this->assertSame('Budi Santoso', $employee->nama_lengkap);
        $this->assertSame('single', $employee->marital_status);
        $this->assertSame($this->company->id, $employee->company_id);
        $this->assertSame($jabatan->id, $employee->job_position_id);
        $this->assertSame($pangkat->id, $employee->pangkat_id);
        $this->assertSame('2024-01-15', $employee->joining_date->toDateString());
        $this->assertSame('aktif', $employee->status);

        $career = $employee->careers->sole();
        $this->assertSame($jabatan->id, $career->job_position_id);
        $this->assertSame($pangkat->id, $career->pangkat_id, 'Baris aktif memuat snapshot pangkat/kelas.');
        $this->assertSame($kelas->id, $career->kelas_id);
    }

    public function test_user_without_profile_gets_employee_with_name_only(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $employee = $this->employeeOf($user);

        $this->assertSame('Budi Santoso', $employee->nama_lengkap);
        $this->assertNull($employee->identity);
        $this->assertCount(0, $employee->addresses);
    }

    public function test_creating_profile_copies_identity_bank_and_address(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'no_nik' => '3201000000000001', 'bank_name' => 'BCA', 'bank_account_number' => '123',
            'bank_account_name' => 'Budi', 'address' => 'Jl. Mawar 1', 'village' => 'Kelurahan A', 'phone' => '0811',
        ]);

        $employee = $this->employeeOf($user);

        $this->assertSame('0811', $employee->phone);
        $this->assertSame('3201000000000001', $employee->identity->no_nik);
        $this->assertSame('BCA', $employee->identity->bank_name);
        $this->assertSame('123', $employee->identity->bank_account_number);
        $this->assertSame('Jl. Mawar 1', $employee->primaryAddress->address);
        $this->assertSame('utama', $employee->primaryAddress->type);
    }

    public function test_editing_profile_updates_employee(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $profile = $user->profile()->create([]);
        $pangkat = Pangkats::create(['name' => 'P2', 'company_id' => $this->company->id]);

        $profile->update(['pangkat_id' => $pangkat->id, 'joining_date' => '2023-03-01', 'phone' => '0899']);

        $employee = $this->employeeOf($user);
        $this->assertSame($pangkat->id, $employee->pangkat_id);
        $this->assertSame('2023-03-01', $employee->joining_date->toDateString());
        $this->assertSame('0899', $employee->phone);
        $this->assertSame(1, Employee::where('user_id', $user->id)->count());
    }

    public function test_editing_profile_via_user_service_updates_employee(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['phone' => '0811', 'bank_account_number' => '123']);

        // Jalur edit tab Data Diri: UserService -> UserRepository::updateProfileUser (update massal
        // lewat relasi, TIDAK memicu event model - sinkron dipanggil manual di repository).
        app(UserService::class)->updateUserProfile($user, ['phone' => '0899', 'bank_account_number' => '999']);

        $employee = $this->employeeOf($user);
        $this->assertSame('0899', $employee->phone);
        $this->assertSame('999', $employee->identity->bank_account_number);
    }

    public function test_changing_user_name_updates_employee_name(): void
    {
        $user = User::factory()->create(['name' => 'Lama']);

        $user->update(['name' => 'Baru']);

        $this->assertSame('Baru', $this->employeeOf($user)->nama_lengkap);
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

        $employee = $this->employeeOf($user);
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

        $this->assertSame('nonaktif', $this->employeeOf($user)->status);
    }

    public function test_super_admin_accounts_get_no_employee(): void
    {
        $user = User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0']); // NON_KARYAWAN, bukan payroll-eligible

        $this->assertSame(0, Employee::where('user_id', $user->id)->count());
    }

    public function test_profile_documents_are_copied_updated_and_removed(): void
    {
        $user = User::factory()->create();
        $ktp = UserProfileDocument::create(['user_id' => $user->id, 'label' => 'KTP', 'file_path' => 'lampiran/ktp-1.enc']);
        UserProfileDocument::create(['user_id' => $user->id, 'label' => 'STR', 'file_path' => 'lampiran/str-1.enc']);

        $employee = $this->employeeOf($user);
        $this->assertEqualsCanonicalizing(['KTP', 'STR'], $employee->documents->pluck('type')->all());
        $this->assertSame('lampiran/ktp-1.enc', $employee->documents->firstWhere('type', 'KTP')->file_path, 'file_path sama persis, file tidak dipindah.');

        $ktp->update(['file_path' => 'lampiran/ktp-2.enc']);
        $this->assertSame('lampiran/ktp-2.enc', $employee->documents()->where('type', 'KTP')->value('file_path'));
        $this->assertSame(2, $employee->documents()->count());

        $ktp->delete();
        $this->assertSame(['STR'], $employee->documents()->pluck('type')->all());
    }

    public function test_backfill_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $jabatan = $this->position('Perawat');
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->profile()->create(['job_position_id' => $jabatan->id, 'no_nik' => '3201000000000001']);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-01', 'status' => 'active']);
        EmployeeCareer::query()->delete();
        Employee::query()->delete(); // simulasi data lama sebelum employees terisi

        $this->artisan('identity:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Employee::count());

        $this->artisan('identity:backfill')->assertSuccessful();
        $this->artisan('identity:backfill-employees')->assertSuccessful(); // nama lama tetap jalan (alias)

        $this->assertSame(1, Employee::where('user_id', $user->id)->count());
        $this->assertSame(1, EmployeeCareer::count());
        $this->assertSame('3201000000000001', $this->employeeOf($user)->identity->no_nik);
    }
}
