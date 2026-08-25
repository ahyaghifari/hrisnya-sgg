<?php

namespace Tests\Feature\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 4 — Keputusan Direktur, WAJIB lewat Approval Engine (bukan kolom ditulis langsung). */
class DirectorDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleSikartap(Companies $company): Sikartap
    {
        $applicant = User::factory()->create(['company_id' => $company->id]);

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

    /** Direktur butuh permission Shield ViewAny/View:Sikartap buat akses halaman
     * Filament-nya (canAccess() gate) - beda dari izin approve itu sendiri yang
     * dicek terpisah lewat ApprovalPermissionService::canApprove(). */
    private function grantSikartapViewPermission(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'Direktur'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);
    }

    private function makeFlow(Companies $company, User $approver): MasterApprovalFlows
    {
        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value,
            'name' => 'Alur Keputusan Direktur',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        return $flow;
    }

    public function test_cannot_submit_when_not_eligible(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $applicant = User::factory()->create(['company_id' => $company->id]);
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);

        $this->expectExceptionMessage('belum memenuhi syarat');

        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);
    }

    public function test_submit_creates_approval_snapshot(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $direktur = User::factory()->create(['company_id' => $company->id]);
        $this->makeFlow($company, $direktur);

        $hrd = $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);

        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $sikartap->refresh();
        $this->assertNotNull($sikartap->approvalSnapshot);
        $this->assertSame('in_review', $sikartap->approvalSnapshot->status);
    }

    public function test_ajukan_action_hidden_when_not_eligible_or_already_submitted(): void
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
        $direktur = User::factory()->create(['company_id' => $company->id]);
        $this->makeFlow($company, $direktur);

        $hrd = $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $this->grantSikartapViewPermission($direktur);
        $this->actingAs($direktur);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('approveDirektur')
            ->callAction('approveDirektur', data: ['note' => 'Disetujui, kinerja baik.']);

        $sikartap->refresh();
        $this->assertSame('approved', $sikartap->status_keputusan_direktur);
        $this->assertSame('approved', $sikartap->approvalSnapshot->status);
    }

    public function test_director_can_reject_via_filament_action(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $direktur = User::factory()->create(['company_id' => $company->id]);
        $this->makeFlow($company, $direktur);

        $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        $this->grantSikartapViewPermission($direktur);
        $this->actingAs($direktur);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->callAction('rejectDirektur', data: ['note' => 'Belum memenuhi kualifikasi.']);

        $sikartap->refresh();
        $this->assertSame('rejected', $sikartap->status_keputusan_direktur);
        $this->assertSame('rejected', $sikartap->approvalSnapshot->status);
    }

    public function test_non_approver_cannot_see_decision_actions(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $direktur = User::factory()->create(['company_id' => $company->id]);
        $this->makeFlow($company, $direktur);

        $hrd = $this->actingAsHrd($company);
        $sikartap = $this->makeEligibleSikartap($company);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('approveDirektur')
            ->assertActionHidden('rejectDirektur');
    }
}
