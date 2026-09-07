<?php

namespace Tests\Feature\Sikartap;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 4 — Keputusan Direktur, LEPAS Approval Engine (2026-09-07). Direktur = pemegang aktif
 * jabatan Setting `sikartap_direktur_job_position_id` perusahaan pelamar. */
class DirectorDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleSikartap(Companies $company): Sikartap
    {
        $applicant = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create([
            'user_id' => $applicant->id,
            'joining_date' => now()->subYears(2)->toDateString(),
        ]);

        $sikartap = Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
            'status_mcu' => 'fit',
        ]);

        $this->assertTrue($sikartap->isEligibleForDirectorDecision());

        return $sikartap;
    }

    private function actingAsHrd(Companies $company): User
    {
        $hrd = User::factory()->create(['company_id' => $company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    /** Buat jabatan Direktur + pemegang aktif, daftarkan di Setting perusahaan. */
    private function makeDirektur(Companies $company): User
    {
        $dept = OrgStructure::create([
            'company_id' => $company->id,
            'name' => 'Direksi',
            'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value,
        ]);

        $pos = JobPositions::create([
            'company_id' => $company->id,
            'name' => 'Direktur Utama',
            'planned_manpower' => 1,
            'org_structure_id' => $dept->id,
        ]);

        $direktur = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create([
            'user_id' => $direktur->id,
            'job_position_id' => $pos->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        // Akses halaman Filament ViewSikartap butuh View:Sikartap (Filament canView gate),
        // terpisah dari izin MEMUTUSKAN yang di-resolve dari jabatan Direktur di atas.
        $role = Role::firstOrCreate(['name' => 'Direktur'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
        ]);
        $direktur->assignRole($role);

        Setting::updateOrCreate(
            ['key' => SikartapDirectorDecisionService::SETTING_KEY, 'company_id' => $company->id],
            ['module' => 'sikartap', 'type' => 'string', 'label' => 'Jabatan Direktur', 'value' => $pos->id],
        );

        return $direktur;
    }

    public function test_cannot_submit_when_not_eligible(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $applicant = User::factory()->create(['company_id' => $company->id]);
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);

        $this->expectExceptionMessage('belum memenuhi syarat');

        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);
    }

    public function test_cannot_submit_when_no_director_configured(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);

        $this->expectExceptionMessage('Jabatan Direktur');

        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);
    }

    public function test_submit_sets_status_diajukan(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->makeDirektur($company);

        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);

        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $sikartap->refresh();
        $this->assertSame('diajukan', $sikartap->status_keputusan_direktur);
        $this->assertNotNull($sikartap->keputusan_direktur_diajukan_at);
    }

    public function test_ajukan_action_hidden_when_not_eligible(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAsHrd($company);
        $applicant = User::factory()->create(['company_id' => $company->id]);
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('ajukanKeputusanDirektur');
    }

    public function test_director_can_approve_via_filament_action(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $direktur = $this->makeDirektur($company);

        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $this->actingAs($direktur);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('approveDirektur')
            ->callAction('approveDirektur', data: ['note' => 'Disetujui, kinerja baik.']);

        $sikartap->refresh();
        $this->assertSame('approved', $sikartap->status_keputusan_direktur);
        $this->assertSame($direktur->id, $sikartap->keputusan_direktur_by);
        $this->assertNotNull($sikartap->user->profile->tmt_tetap);
    }

    public function test_director_can_reject_via_filament_action(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $direktur = $this->makeDirektur($company);

        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $this->actingAs($direktur);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->callAction('rejectDirektur', data: ['note' => 'Belum memenuhi kualifikasi.']);

        $sikartap->refresh();
        $this->assertSame('rejected', $sikartap->status_keputusan_direktur);
        $this->assertSame('Belum memenuhi kualifikasi.', $sikartap->keputusan_direktur_note);
    }

    public function test_non_director_cannot_see_decision_actions(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->makeDirektur($company);

        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('approveDirektur')
            ->assertActionHidden('rejectDirektur');
    }
}
