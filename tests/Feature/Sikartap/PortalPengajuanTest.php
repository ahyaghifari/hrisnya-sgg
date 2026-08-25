<?php

namespace Tests\Feature\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Sikartap\DashboardSikartapPage;
use App\Livewire\Sikartap\PengajuanDetailPage;
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

/** Portal HRD/Direktur (rewrite native Livewire dari Filament SikartapResource/ViewSikartap). */
class PortalPengajuanTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    private function actingAsHrd(): User
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hrd = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    private function makeApplicant(): User
    {
        return User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_list_page_blocked_without_permission(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(DashboardSikartapPage::class)->assertForbidden();
    }

    public function test_list_page_shows_applicant(): void
    {
        $this->actingAsHrd();
        $applicant = $this->makeApplicant();
        Sikartap::create(['user_id' => $applicant->id]);

        Livewire::test(DashboardSikartapPage::class)
            ->assertSee($applicant->name);
    }

    /** Regresi: gate filter company sempat salah pakai canBrowseAllCompanies() (role Direktur +
     * global role) alih-alih canFilterByCompany() (permission LIHAT_PERUSAHAAN_LAIN, pola sama
     * MyEmployees) - filter tidak pernah muncul buat HRD biasa yang company-nya lebih dari 1. */
    public function test_company_filter_visible_for_user_with_permission(): void
    {
        $hrd = $this->actingAsHrd();
        $hrd->givePermissionTo(Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']));

        Livewire::test(DashboardSikartapPage::class)
            ->assertSee('Semua perusahaan');
    }

    public function test_company_filter_hidden_without_permission(): void
    {
        $this->actingAsHrd();

        Livewire::test(DashboardSikartapPage::class)
            ->assertDontSee('Semua perusahaan');
    }

    public function test_detail_page_approve_all_berkas(): void
    {
        $this->actingAsHrd();
        $sikartap = Sikartap::create(['user_id' => $this->makeApplicant()->id]);

        foreach (\App\Enum\Sikartap\SikartapDocumentType::cases() as $type) {
            if ($type->isRequired()) {
                $sikartap->documents()->create(['doc_type' => $type->value, 'file_path' => 'dummy.pdf', 'status' => 'pending']);
            }
        }

        Livewire::test(PengajuanDetailPage::class, ['sikartap' => $sikartap])
            ->call('approveAllBerkas');

        $sikartap->refresh();
        $this->assertSame('approved', $sikartap->status_berkas);
        $this->assertTrue((bool) $sikartap->berkas_lengkap);
    }

    public function test_detail_page_full_flow_through_director_decision(): void
    {
        $direktur = null;
        $this->actingAsHrd();
        $direktur = User::factory()->create(['company_id' => $this->company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value,
            'name' => 'Alur Keputusan Direktur',
            'flow_type' => 'default',
            'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $direktur->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
        ]);

        $component = Livewire::test(PengajuanDetailPage::class, ['sikartap' => $sikartap]);

        $component->set('testScheduleForm', ['tanggal_tes' => '2026-09-01', 'waktu_tes' => '09:00', 'tempat_tes' => 'Aula', 'keterangan' => null])
            ->call('saveTestSchedule');
        $component->set('testResultForm', ['nilai_tes' => 90, 'nilai_psikotes' => 85, 'status_tes' => 'lulus', 'catatan_tes' => null])
            ->call('saveTestResult');
        $component->set('interviewScheduleForm', ['tanggal_wawancara' => '2026-09-05', 'waktu_wawancara' => '10:00', 'tempat_wawancara' => 'Ruang HRD', 'pewawancara' => 'Nadya', 'pewawancara_2' => null, 'keterangan' => null])
            ->call('saveInterviewSchedule');
        $component->set('interviewResultForm', ['status_wawancara' => 'disarankan', 'catatan_wawancara' => null])
            ->call('saveInterviewResult');

        $sikartap->refresh();
        $sikartap->mcuDoctorExamination()->create(['rekomendasi' => 'Fit untuk bekerja']);

        $component = Livewire::test(PengajuanDetailPage::class, ['sikartap' => $sikartap]);
        $component->set('mcuDecisionForm', ['status_mcu' => 'fit', 'catatan_mcu' => 'Fit'])
            ->call('saveMcuDecision');

        $sikartap->refresh();
        $this->assertSame('fit', $sikartap->status_mcu);
        $this->assertTrue($sikartap->isEligibleForDirectorDecision());

        $component = Livewire::test(PengajuanDetailPage::class, ['sikartap' => $sikartap]);
        $component->call('ajukanKeputusanDirektur');

        $sikartap->refresh();
        $this->assertNotNull($sikartap->approvalSnapshot);

        // Direktur perlu ViewAny/View:Sikartap juga buat akses halaman ini (canAccess gate).
        $direkturRole = Role::firstOrCreate(['name' => 'Direktur'], ['guard_name' => 'web']);
        $direkturRole->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
        ]);
        $direktur->assignRole($direkturRole);
        $this->actingAs($direktur);

        Livewire::test(PengajuanDetailPage::class, ['sikartap' => $sikartap])
            ->set('directorNote', 'Disetujui, kinerja baik.')
            ->call('confirmApproveDirektur');

        $sikartap->refresh();
        $this->assertSame('approved', $sikartap->status_keputusan_direktur);
    }
}
