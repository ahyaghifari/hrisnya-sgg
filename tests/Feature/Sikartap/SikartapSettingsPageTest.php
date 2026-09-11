<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Livewire\Sikartap\SikartapSettingsPage;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Setting;
use App\Models\Sikartap\SikartapIdeInovasiReviewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** 1 reviewer per Departemen, 1 tombol Simpan buat semua sekaligus (2026-08-26, instruksi
 * user) - tolak simpan kalau ADA Departemen yang belum terisi. */
class SikartapSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value, 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'Sikartap Settings Test', 'guard_name' => 'web']);
        $role->syncPermissions([
            CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value,
            CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value,
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(SikartapSettingsPage::class)
            ->assertForbidden();
    }

    public function test_cannot_save_when_a_department_has_no_reviewer(): void
    {
        $admin = $this->actingAsAdmin();
        $company = $this->makeCompany();
        $admin->update(['company_id' => $company->id]);

        $deptA = OrgStructure::create(['company_id' => $company->id, 'name' => 'Departemen A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        OrgStructure::create(['company_id' => $company->id, 'name' => 'Departemen B', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $reviewer = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($admin)
            ->test(SikartapSettingsPage::class)
            ->set('companyId', $company->id)
            ->set("reviewerUserIds.{$deptA->id}", $reviewer->id)
            // Departemen B sengaja dibiarkan kosong.
            ->call('saveReviewers');

        $this->assertDatabaseMissing('sikartap_ide_inovasi_reviewers', ['org_structure_id' => $deptA->id]);
    }

    public function test_saves_all_departments_when_fully_filled(): void
    {
        $admin = $this->actingAsAdmin();
        $company = $this->makeCompany();
        $admin->update(['company_id' => $company->id]);

        $deptA = OrgStructure::create(['company_id' => $company->id, 'name' => 'Departemen A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $deptB = OrgStructure::create(['company_id' => $company->id, 'name' => 'Departemen B', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $reviewerA = User::factory()->create(['company_id' => $company->id]);
        $reviewerB = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($admin)
            ->test(SikartapSettingsPage::class)
            ->set('companyId', $company->id)
            ->set("reviewerUserIds.{$deptA->id}", $reviewerA->id)
            ->set("reviewerUserIds.{$deptB->id}", $reviewerB->id)
            ->call('saveReviewers');

        $this->assertDatabaseHas('sikartap_ide_inovasi_reviewers', ['org_structure_id' => $deptA->id, 'user_id' => $reviewerA->id]);
        $this->assertDatabaseHas('sikartap_ide_inovasi_reviewers', ['org_structure_id' => $deptB->id, 'user_id' => $reviewerB->id]);
    }

    public function test_saving_again_replaces_previous_reviewer_not_duplicates(): void
    {
        $admin = $this->actingAsAdmin();
        $company = $this->makeCompany();
        $admin->update(['company_id' => $company->id]);

        $dept = OrgStructure::create(['company_id' => $company->id, 'name' => 'Departemen A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $oldReviewer = User::factory()->create(['company_id' => $company->id]);
        $newReviewer = User::factory()->create(['company_id' => $company->id]);

        SikartapIdeInovasiReviewer::create([
            'company_id' => $company->id,
            'org_structure_id' => $dept->id,
            'user_id' => $oldReviewer->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SikartapSettingsPage::class)
            ->set('companyId', $company->id)
            ->set("reviewerUserIds.{$dept->id}", $newReviewer->id)
            ->call('saveReviewers');

        $this->assertSame(1, SikartapIdeInovasiReviewer::where('org_structure_id', $dept->id)->count());
        $this->assertDatabaseHas('sikartap_ide_inovasi_reviewers', ['org_structure_id' => $dept->id, 'user_id' => $newReviewer->id]);
    }

    /** Jabatan pendukung persetujuan presentasi orientasi boleh dari perusahaan LAIN (mis. holding). */
    public function test_presentation_approver_can_be_cross_company_job_position(): void
    {
        $admin = $this->actingAsAdmin();
        $company = $this->makeCompany();
        $otherCompany = Companies::create(['code' => 'OTH', 'name' => 'Other Co', 'location' => 'Test', 'is_active' => true]);
        $admin->update(['company_id' => $company->id]);

        $dept = OrgStructure::create(['company_id' => $otherCompany->id, 'name' => 'Holding', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $pos = \App\Models\Master\JobPositions::create([
            'company_id' => $otherCompany->id,
            'name' => 'Direktur Holding',
            'planned_manpower' => 1,
            'org_structure_id' => $dept->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SikartapSettingsPage::class)
            ->set('companyId', $company->id)
            ->assertSee('Direktur Holding')
            ->set('presentationApproverJobPositionId', $pos->id)
            ->call('savePresentationApprover');

        $this->assertSame(
            $pos->id,
            app(\App\Services\OrientationService::class)->presentationApproverJobPositionId($company->id),
        );
    }
}
