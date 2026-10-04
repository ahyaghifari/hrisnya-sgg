<?php

namespace Tests\Feature\Identity;

use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Izin menyetujui untuk penyetuju tipe job_position dan role+scope departemen, dengan jabatan
 * dibaca dari employees (lewat EmployeeContext). Hasilnya harus sama dengan perilaku lama.
 */
class ApprovalPermissionEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private function position(string $name, ?string $orgId = null): JobPositions
    {
        $this->company ??= Companies::create(['code' => 'APR', 'name' => 'Company Apr', 'location' => 'Test', 'is_active' => true]);

        return JobPositions::create(['company_id' => $this->company->id, 'name' => $name, 'planned_manpower' => 1, 'org_structure_id' => $orgId]);
    }

    private function userAt(JobPositions $position): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->profile()->create(['job_position_id' => $position->id]);

        return $user->fresh();
    }

    /** Aktivitas approval pending untuk satu snapshot. @return string snapshot id */
    private function pending(string $type, string $ref, ?string $scopeType = null, ?string $scopeRef = null): string
    {
        $flow = DB::table('m_approval_flows')->value('id') ?? tap(Str::ulid()->toString(), fn ($id) => DB::table('m_approval_flows')->insert([
            'id' => $id, 'company_id' => $this->company->id, 'name' => 'F', 'module' => 'attendance_request', 'created_at' => now(), 'updated_at' => now(),
        ]));

        DB::table('approval_snapshots')->insert([
            'id' => $snapshot = Str::ulid()->toString(), 'module_type' => 'attendance_request', 'module_id' => Str::ulid()->toString(),
            'flow_id' => $flow, 'flow_name' => 'F', 'steps_snapshot' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('approval_activities')->insert([
            'id' => Str::ulid()->toString(), 'snapshot_id' => $snapshot, 'approver_type' => $type, 'approver_ref_id' => $ref,
            'scope_type' => $scopeType, 'scope_ref_id' => $scopeRef, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $snapshot;
    }

    public function test_job_position_approver_is_allowed_only_for_the_matching_position(): void
    {
        $kepala = $this->position('Kepala Ruangan');
        $staf = $this->position('Perawat');
        $matching = $this->userAt($kepala);
        $other = $this->userAt($staf);

        $snapshot = $this->pending('job_position', $kepala->id);
        $service = app(ApprovalPermissionService::class);

        $this->assertTrue($service->canApprove($snapshot, $matching));
        $this->assertFalse($service->canApprove($snapshot, $other));
        $this->assertNull($service->approvalDeniedReason($snapshot, $matching), 'Penyetuju yang benar tidak ditolak.');
        $this->assertNotNull($service->approvalDeniedReason($snapshot, $other));

        // Daftar approval yang menunggu juga mengikuti jabatan yang sama.
        $this->assertCount(1, $service->pendingApprovalsFor($matching, 'attendance_request'));
        $this->assertCount(0, $service->pendingApprovalsFor($other, 'attendance_request'));
    }

    public function test_job_position_comes_from_employees_when_it_differs_from_the_profile(): void
    {
        $lama = $this->position('Perawat');
        $baru = $this->position('Kepala Ruangan');
        $user = $this->userAt($lama);

        // employees sudah menunjuk jabatan baru; profil belum (salinan lebih mutakhir).
        DB::table('employees')->where('user_id', $user->id)->update(['job_position_id' => $baru->id]);

        $snapshot = $this->pending('job_position', $baru->id);

        $this->assertTrue(app(ApprovalPermissionService::class)->canApprove($snapshot, $user));
    }

    public function test_falls_back_to_profile_for_account_without_employee_row(): void
    {
        $kepala = $this->position('Kepala Ruangan');
        $admin = User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0', 'company_id' => $this->company->id]); // tanpa employees
        $admin->profile()->create(['job_position_id' => $kepala->id]);

        $snapshot = $this->pending('job_position', $kepala->id);

        $this->assertTrue(app(ApprovalPermissionService::class)->canApprove($snapshot, $admin->fresh()));
    }

    public function test_notification_recipient_for_job_position_approver_follows_employees(): void
    {
        $kepala = $this->position('Kepala Ruangan');
        $lain = $this->position('Perawat');
        $head = $this->userAt($kepala);
        $this->userAt($lain);

        $snapshot = ApprovalSnapshots::findOrFail($this->pending('job_position', $kepala->id));
        $service = app(NotificationService::class);

        $this->assertSame($head->id, $service->getFirstApproverFromSnapshot($snapshot)?->id);

        // employees pindah jabatan, profil belum: penerima mengikuti employees.
        $mutated = $this->userAt($lain);
        DB::table('employees')->where('user_id', $mutated->id)->update(['job_position_id' => $kepala->id]);
        DB::table('employees')->where('user_id', $head->id)->update(['job_position_id' => $lain->id]);

        $this->assertSame($mutated->id, $service->getFirstApproverFromSnapshot($snapshot)?->id);
    }

    public function test_notification_recipient_falls_back_to_profile_for_account_without_employee(): void
    {
        $kepala = $this->position('Kepala Ruangan');
        // User biasa yang salinan employees-nya belum ada/tertinggal: tetap ketemu lewat profil.
        $head = $this->userAt($kepala);
        DB::table('employees')->where('user_id', $head->id)->delete();

        $snapshot = ApprovalSnapshots::findOrFail($this->pending('job_position', $kepala->id));

        $this->assertSame($head->id, app(NotificationService::class)->getFirstApproverFromSnapshot($snapshot)?->id);
    }

    public function test_notification_recipient_for_role_with_department_scope(): void
    {
        $this->position('x');
        $deptA = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Dept A', 'level' => 'DEPARTMENT']);
        $deptB = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Dept B', 'level' => 'DEPARTMENT']);
        $role = Role::create(['name' => 'Penyetuju Dept', 'guard_name' => 'web']);

        $inB = $this->userAt($this->position('Staf B', $deptB->id));
        $inB->assignRole($role);
        $inA = $this->userAt($this->position('Staf A', $deptA->id));
        $inA->assignRole($role);

        $snapshot = ApprovalSnapshots::findOrFail($this->pending('role', (string) $role->id, 'specific_department', $deptA->id));

        $this->assertSame($inA->id, app(NotificationService::class)->getFirstApproverFromSnapshot($snapshot)?->id);
    }

    public function test_role_with_department_scope_follows_the_department_of_the_job_position(): void
    {
        $this->position('x');
        $deptA = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Dept A', 'level' => 'DEPARTMENT']);
        $divA = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Div A', 'level' => 'DIVISION', 'parent_id' => $deptA->id]);
        $deptB = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Dept B', 'level' => 'DEPARTMENT']);

        $role = Role::create(['name' => 'Penyetuju Dept', 'guard_name' => 'web']);
        $inA = $this->userAt($this->position('Staf Div A', $divA->id));
        $inA->assignRole($role);
        $inB = $this->userAt($this->position('Staf Dept B', $deptB->id));
        $inB->assignRole($role);

        $snapshot = $this->pending('role', (string) $role->id, 'specific_department', $deptA->id);
        $service = app(ApprovalPermissionService::class);

        $this->assertTrue($service->canApprove($snapshot, $inA), 'Divisi di bawah Dept A masuk scope Dept A.');
        $this->assertFalse($service->canApprove($snapshot, $inB));
    }
}
