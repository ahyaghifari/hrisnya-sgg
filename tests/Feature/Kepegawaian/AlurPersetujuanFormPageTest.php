<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\AlurPersetujuanFormPage;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AlurPersetujuanFormPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermission(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value, 'guard_name' => 'web']);
        $role = SpatieRole::firstOrCreate(['name' => 'Alur Persetujuan Form Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'APF', 'name' => 'RS Form Test', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->assertForbidden();
    }

    public function test_create_default_flow_with_steps(): void
    {
        $company = Companies::create(['code' => 'APF2', 'name' => 'RS Form Test 2', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $approverRole = SpatieRole::create(['name' => 'Manajer Test', 'guard_name' => 'web', 'company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Cuti Standar')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'default')
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $approverRole->id)
            ->set('steps.0.is_final', true)
            ->call('save')
            ->assertRedirect(route('kepegawaian.alur-persetujuan'));

        $flow = MasterApprovalFlows::where('name', 'Alur Cuti Standar')->first();
        $this->assertNotNull($flow);
        $this->assertSame($company->id, $flow->company_id);
        $this->assertSame(1, $flow->steps()->count());
        $this->assertDatabaseHas('m_approval_flow_steps', [
            'flow_id' => $flow->id,
            'approver_type' => 'role',
            'approver_ref_id' => $approverRole->id,
            'is_final' => true,
        ]);
    }

    public function test_create_specific_flow_saves_job_position_assignments(): void
    {
        $company = Companies::create(['code' => 'APF3', 'name' => 'RS Form Test 3', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $job = JobPositions::create(['name' => 'Kepala Ruangan', 'company_id' => $company->id, 'status' => 'approved']);
        $approverRole = SpatieRole::create(['name' => 'Direktur Test', 'guard_name' => 'web', 'company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Khusus Karu')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'specific')
            ->set('assignmentJobPositions', [$job->id])
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $approverRole->id)
            ->call('save')
            ->assertRedirect(route('kepegawaian.alur-persetujuan'));

        $flow = MasterApprovalFlows::where('name', 'Alur Khusus Karu')->first();
        $this->assertNotNull($flow);
        $this->assertSame(1, $flow->assignments()->count());
        $this->assertDatabaseHas('m_approval_flow_assignments', [
            'approval_flow_id' => $flow->id,
            'assignable_id' => $job->id,
        ]);
    }

    public function test_specific_flow_saves_supervisor_step_without_approver_ref(): void
    {
        $company = Companies::create(['code' => 'APFS', 'name' => 'RS Sup Test', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $job = JobPositions::create(['name' => 'Staff', 'company_id' => $company->id, 'status' => 'approved']);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Atasan Langsung')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'specific')
            ->set('assignmentJobPositions', [$job->id])
            ->set('steps.0.approver_type', 'supervisor')
            ->set('steps.0.supervisor_level', 2)
            ->set('steps.0.is_final', true)
            ->call('save')
            ->assertRedirect(route('kepegawaian.alur-persetujuan'));

        $flow = MasterApprovalFlows::where('name', 'Alur Atasan Langsung')->firstOrFail();
        $this->assertSame(1, $flow->steps()->count());
        $this->assertDatabaseHas('m_approval_flow_steps', [
            'flow_id' => $flow->id,
            'approver_type' => 'supervisor',
            'approver_ref_id' => null,
            'supervisor_level' => 2,
            'is_final' => true,
        ]);
    }

    public function test_default_flow_saves_sub_module_buckets(): void
    {
        $company = Companies::create(['code' => 'APFSM', 'name' => 'RS SubModule', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $approverRole = SpatieRole::create(['name' => 'Approver SM', 'guard_name' => 'web', 'company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Cuti + Izin Seharian')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'default')
            ->set('subModule', ['leave', 'tidak_masuk_seharian'])
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $approverRole->id)
            ->set('steps.0.is_final', true)
            ->call('save')
            ->assertRedirect(route('kepegawaian.alur-persetujuan'));

        $flow = MasterApprovalFlows::where('name', 'Alur Cuti + Izin Seharian')->firstOrFail();
        $this->assertSame('leave,tidak_masuk_seharian', $flow->sub_module);
    }

    public function test_two_default_flows_with_overlapping_bucket_rejected(): void
    {
        $company = Companies::create(['code' => 'APFOV', 'name' => 'RS Overlap', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $approverRole = SpatieRole::create(['name' => 'Approver OV', 'guard_name' => 'web', 'company_id' => $company->id]);

        MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            'sub_module' => 'leave',
            'name' => 'Alur Cuti',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Cuti Lagi')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'default')
            ->set('subModule', ['leave', 'overtime'])
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $approverRole->id)
            ->set('steps.0.is_final', true)
            ->call('save')
            ->assertHasErrors('module');

        $this->assertNull(MasterApprovalFlows::where('name', 'Alur Cuti Lagi')->first());
    }

    public function test_supervisor_step_rejected_on_default_flow(): void
    {
        $company = Companies::create(['code' => 'APFD', 'name' => 'RS Sup Default', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class)
            ->set('name', 'Alur Salah')
            ->set('module', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->set('flowType', 'default')
            ->set('steps.0.approver_type', 'supervisor')
            ->set('steps.0.is_final', true)
            ->call('save')
            ->assertHasErrors('steps');

        $this->assertNull(MasterApprovalFlows::where('name', 'Alur Salah')->first());
    }

    public function test_edit_mode_locks_company(): void
    {
        $company = Companies::create(['code' => 'APF4', 'name' => 'RS Form Test 4', 'location' => 'Test', 'is_active' => true]);
        $otherCompany = Companies::create(['code' => 'APF5', 'name' => 'RS Form Test 5', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);
        $user->givePermissionTo(CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);

        $flow = MasterApprovalFlows::create(['company_id' => $company->id, 'name' => 'Alur Lama', 'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value, 'flow_type' => 'default', 'is_active' => true]);
        $approverRole = SpatieRole::create(['name' => 'Approver Lama', 'guard_name' => 'web', 'company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class, ['flow' => $flow])
            ->set('companyId', $otherCompany->id) // coba diakalin
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $approverRole->id)
            ->call('save');

        $this->assertSame($company->id, $flow->fresh()->company_id, 'company_id tidak boleh berubah walau di-set paksa.');
    }

    public function test_edit_updates_steps_replacing_old_ones(): void
    {
        $company = Companies::create(['code' => 'APF6', 'name' => 'RS Form Test 6', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);
        $flow = MasterApprovalFlows::create(['company_id' => $company->id, 'name' => 'Alur Edit', 'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value, 'flow_type' => 'default', 'is_active' => true]);
        $roleA = SpatieRole::create(['name' => 'Role A', 'guard_name' => 'web', 'company_id' => $company->id]);
        $roleB = SpatieRole::create(['name' => 'Role B', 'guard_name' => 'web', 'company_id' => $company->id]);
        $flow->steps()->create(['step_order' => 1, 'approver_type' => 'role', 'approver_ref_id' => $roleA->id, 'approval_mode' => 'any', 'scope_type' => 'same_department', 'is_final' => true]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanFormPage::class, ['flow' => $flow])
            ->assertSet('steps.0.approver_ref_id', $roleA->id)
            ->call('removeStep', 0)
            ->call('addStep')
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', $roleB->id)
            ->set('steps.0.is_final', true)
            ->call('save');

        $this->assertSame(1, $flow->steps()->count());
        $this->assertDatabaseHas('m_approval_flow_steps', ['flow_id' => $flow->id, 'approver_ref_id' => $roleB->id]);
        $this->assertDatabaseMissing('m_approval_flow_steps', ['flow_id' => $flow->id, 'approver_ref_id' => $roleA->id]);
    }
}
