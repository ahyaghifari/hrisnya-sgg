<?php

namespace Tests\Feature\Identity;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** identity:verify = parity check hanya-baca antara data lama dan salinan employees. */
class VerifyIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private function karyawan(string $nip): User
    {
        $this->company ??= Companies::create(['code' => 'VER', 'name' => 'Company Ver', 'location' => 'Test', 'is_active' => true]);
        $jabatan = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Perawat ' . $nip, 'planned_manpower' => 1]);

        $user = User::factory()->create(['company_id' => $this->company->id, 'nip' => $nip]);
        $user->profile()->create(['job_position_id' => $jabatan->id, 'no_nik' => '3201' . $nip, 'joining_date' => '2024-01-15']);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $jabatan->id, 'start_date' => '2024-01-15', 'status' => 'active']);

        return $user;
    }

    public function test_passes_when_copies_match_old_data(): void
    {
        $this->karyawan('V001');
        $this->karyawan('V002');

        $this->artisan('identity:verify')
            ->expectsOutputToContain('Semua cocok.')
            ->assertSuccessful();
    }

    public function test_reports_nip_when_employee_copy_drifts(): void
    {
        $this->karyawan('V001');
        $drift = $this->karyawan('V002');

        // Simulasi update massal yang tidak memicu observer: salinan tertinggal.
        DB::table('employees')->where('user_id', $drift->id)->update(['company_id' => null]);

        $this->artisan('identity:verify')
            ->expectsOutputToContain('employees: data kepegawaian: V002')
            ->assertFailed();
    }

    public function test_reports_stale_personal_identity_and_document_copies(): void
    {
        $stale = $this->karyawan('V001');
        $this->karyawan('V002');

        // Simulasi update massal yang tidak memicu observer: salinan data pribadi/identitas tertinggal.
        DB::table('employees')->where('user_id', $stale->id)->update(['phone' => '0000', 'nama_lengkap' => 'Nama Lain']);
        DB::table('employee_identities')->where('employee_id', DB::table('employees')->where('user_id', $stale->id)->value('id'))->update(['no_nik' => '9999']);

        $this->artisan('identity:verify')
            ->expectsOutputToContain('employees: data pribadi: V001')
            ->expectsOutputToContain('employees: identitas & rekening: V001')
            ->assertFailed();
    }

    public function test_reports_missing_employee_and_conflicting_job_sources(): void
    {
        $missing = $this->karyawan('V001');
        $conflict = $this->karyawan('V002');

        DB::table('employees')->where('user_id', $missing->id)->delete();

        // Dua sumber jabatan berbeda: ledger aktif menunjuk jabatan lain dari profil.
        $other = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Lain', 'planned_manpower' => 1]);
        DB::table('user_job_positions')->where('user_id', $conflict->id)->update(['job_position_id' => $other->id]);

        $this->artisan('identity:verify')
            ->expectsOutputToContain('employees: baris ada: V001')
            ->expectsOutputToContain('jabatan: profil = jabatan aktif di riwayat: V002')
            ->assertFailed();
    }

    public function test_verify_writes_nothing(): void
    {
        $this->karyawan('V001');
        $count = fn () => [DB::table('employees')->count(), DB::table('employee_identities')->count(), DB::table('employee_careers')->count()];
        $before = $count();

        $this->artisan('identity:verify');

        $this->assertSame($before, $count());
    }
}
