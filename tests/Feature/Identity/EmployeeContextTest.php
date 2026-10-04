<?php

namespace Tests\Feature\Identity;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Identity\EmployeeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EmployeeContext = titik akses jabatan/organisasi untuk approval (dipindah dari user_profiles ke
 * employees). Hasilnya HARUS sama dengan jalur lama untuk data yang sudah tersinkron, dan jatuh
 * balik ke profil untuk akun tanpa baris employees.
 */
class EmployeeContextTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private function position(string $name, ?string $parentId = null, ?string $orgId = null): JobPositions
    {
        $this->company ??= Companies::create(['code' => 'CTX', 'name' => 'Company Ctx', 'location' => 'Test', 'is_active' => true]);

        return JobPositions::create([
            'company_id' => $this->company->id, 'name' => $name, 'planned_manpower' => 1,
            'parent_id' => $parentId, 'org_structure_id' => $orgId,
        ]);
    }

    private function userWith(?JobPositions $position): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id ?? null]);
        $user->profile()->create(['job_position_id' => $position?->id]);

        return $user->fresh();
    }

    public function test_job_position_comes_from_employees_and_matches_the_profile(): void
    {
        $perawat = $this->position('Perawat');
        $user = $this->userWith($perawat);

        $this->assertSame($perawat->id, EmployeeContext::jobPositionId($user->id));
        $this->assertSame($user->profile->job_position_id, EmployeeContext::jobPositionId($user->id));
        $this->assertNull(EmployeeContext::jobPositionId(null));
    }

    public function test_falls_back_to_profile_for_account_without_employee_row(): void
    {
        $perawat = $this->position('Perawat');
        // Akun yang salinan employees-nya belum ada/tertinggal tetap membaca jabatan dari profil.
        $admin = User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0']);
        $admin->profile()->create(['job_position_id' => $perawat->id]);

        DB::table('employees')->where('user_id', $admin->id)->delete();
        $this->assertSame($perawat->id, EmployeeContext::jobPositionId($admin->id));
    }

    public function test_null_when_user_has_no_job_position_anywhere(): void
    {
        $this->position('Perawat');
        $user = $this->userWith(null);

        $this->assertNull(EmployeeContext::jobPositionId($user->id));
        $this->assertNull(EmployeeContext::departmentId($user->id));
        $this->assertNull(EmployeeContext::orgStructureId($user->id));
    }

    public function test_department_and_org_structure_follow_the_job_position(): void
    {
        $this->position('x'); // memastikan $this->company ada
        $dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Dept', 'level' => 'DEPARTMENT']);
        $div = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Div', 'level' => 'DIVISION', 'parent_id' => $dept->id]);
        $perawat = $this->position('Perawat Div', null, $div->id);
        $user = $this->userWith($perawat);

        $this->assertSame($div->id, EmployeeContext::orgStructureId($user->id));
        $this->assertSame($dept->id, EmployeeContext::departmentId($user->id));
        // Sama persis dengan jalur lama (accessor di profil).
        $this->assertSame($user->profile->department->id, EmployeeContext::departmentId($user->id));
    }

    public function test_supervisor_position_is_resolved_through_employee_job_position(): void
    {
        $kepala = $this->position('Kepala Ruangan');
        $staf = $this->position('Perawat', $kepala->id);
        $user = $this->userWith($staf);

        $repo = new ApprovalSnapshotRepository();

        $this->assertSame($kepala->id, $repo->resolveSupervisorPositionId($user->id));
        $this->assertNull($repo->resolveSupervisorPositionId($user->id, 2), 'Rantai lebih pendek dari level -> null.');
    }
}
