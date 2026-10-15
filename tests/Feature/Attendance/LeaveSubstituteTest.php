<?php

namespace Tests\Feature\Attendance;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\OrgStructurLevel;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pengganti cuti: opsional, harus karyawan aktif lain di unit (org_structure jabatan) yang sama. */
class LeaveSubstituteTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private OrgStructure $unitA;
    private OrgStructure $unitB;
    private OrgStructure $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = new Companies();
        $this->company->forceFill(['id' => '01k8271xa4nq8cezg3dq9vfr14', 'code' => 'SUB', 'name' => 'RSU Syifa Medika Banjarbaru', 'location' => 'Test', 'is_active' => true])->save();
        $approver = User::factory()->create(['company_id' => $this->company->id]);
        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id, 'module' => 'attendance_request',
            'name' => 'Alur Test', 'flow_type' => 'default', 'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user',
            'approver_ref_id' => $approver->id, 'approval_mode' => 'any', 'scope_type' => 'global', 'is_final' => true,
        ]);

        $this->unitA = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Unit A', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $this->unitB = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Unit B', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $this->dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Departemen X', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
    }

    private function employeeIn(OrgStructure $unit, string $name): User
    {
        $job = JobPositions::create(['company_id' => $this->company->id, 'name' => "Jabatan {$name}", 'planned_manpower' => 1, 'org_structure_id' => $unit->id]);
        $user = User::factory()->create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'job_position_id' => $job->id]);

        return $user;
    }

    private function submit(User $employee, string|array|null $substituteIds): void
    {
        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'substitute_user_ids' => (array) $substituteIds,
        ], $employee);
    }

    private function substituteIds(): array
    {
        return AttendanceRequestLeaveDetails::first()->substitutes()->pluck('users.id')->all();
    }

    public function test_multiple_substitutes_are_stored(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $a = $this->employeeIn($this->unitA, 'Rekan A');
        $b = $this->employeeIn($this->unitA, 'Rekan B');

        $this->submit($employee, [$a->id, $b->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->substituteIds());
    }

    public function test_substitute_task_is_stored_only_with_substitutes(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $mate = $this->employeeIn($this->unitA, 'Rekan');
        $svc = app(AttendaceReqService::class);
        $base = [
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
        ];

        $svc->storeLeaveRequest($base + ['substitute_user_ids' => [$mate->id], 'substitute_task' => 'Jaga IGD', 'reason' => 'Acara keluarga'], $employee);
        $this->assertSame('Jaga IGD', AttendanceRequestLeaveDetails::first()->substitute_task);
        $this->assertSame('Acara keluarga', AttendanceRequestLeaveDetails::first()->request->reason);

        AttendanceRequestLeaveDetails::query()->delete();
        $svc->storeLeaveRequest($base + ['substitute_task' => 'Jaga IGD'], $employee);
        $this->assertNull(AttendanceRequestLeaveDetails::first()->substitute_task);
    }

    public function test_substitutes_can_be_added_once_after_submit(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $mate = $this->employeeIn($this->unitA, 'Rekan Unit');
        $other = $this->employeeIn($this->unitB, 'Orang Lain');
        $svc = app(AttendaceReqService::class);

        $this->submit($employee, []);
        $request = AttendanceRequestLeaveDetails::first()->request;

        try {
            $svc->addLeaveSubstitutes($request, $employee, [$other->id], null);
            $this->fail('Pengganti beda unit seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertSame('Pengganti harus karyawan lain di unit yang sama.', $e->getMessage());
        }

        $svc->addLeaveSubstitutes($request, $employee, [$mate->id], 'Jaga IGD');
        $this->assertSame([$mate->id], $this->substituteIds());
        $this->assertSame('Jaga IGD', AttendanceRequestLeaveDetails::first()->substitute_task);

        $this->expectExceptionMessage('Pengganti sudah pernah diisi dan tidak bisa diubah.');
        $svc->addLeaveSubstitutes($request, $employee, [$mate->id], null);
    }

    public function test_more_than_five_substitutes_is_rejected(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $ids = [];
        foreach (range(1, 6) as $n) {
            $ids[] = $this->employeeIn($this->unitA, "Rekan {$n}")->id;
        }

        $this->expectExceptionMessage('Pengganti maksimal 5 orang.');

        $this->submit($employee, $ids);
    }

    public function test_one_substitute_from_other_unit_rejects_whole_list(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $mate = $this->employeeIn($this->unitA, 'Rekan Unit');
        $other = $this->employeeIn($this->unitB, 'Orang Lain');

        $this->expectExceptionMessage('Pengganti harus karyawan lain di unit yang sama.');

        $this->submit($employee, [$mate->id, $other->id]);
    }

    public function test_substitute_must_be_in_same_unit_and_is_stored(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $mate = $this->employeeIn($this->unitA, 'Rekan Unit');
        $this->employeeIn($this->unitB, 'Orang Lain');

        $options = app(AttendaceReqService::class)->unitMates($employee)->pluck('name')->all();
        $this->assertSame(['Rekan Unit'], $options);

        $this->submit($employee, $mate->id);

        $this->assertSame([$mate->id], $this->substituteIds());
    }

    public function test_substitute_from_other_unit_is_rejected(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $other = $this->employeeIn($this->unitB, 'Orang Lain');

        $this->expectExceptionMessage('Pengganti harus karyawan lain di unit yang sama.');

        $this->submit($employee, $other->id);
    }

    /** Atasan boleh memilih bawahannya sebagai pengganti walau bawahan itu di unit/org_structure lain. */
    public function test_supervisor_can_pick_subordinate_from_another_unit(): void
    {
        $bossJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Karu', 'planned_manpower' => 1, 'org_structure_id' => $this->dept->id]);
        $staffJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'org_structure_id' => $this->unitB->id, 'parent_id' => $bossJob->id]);
        $boss = User::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Perawat Bawahan', 'is_active' => true]);
        foreach ([[$boss, $bossJob], [$staff, $staffJob]] as [$u, $job]) {
            UserProfiles::create(['user_id' => $u->id, 'job_position_id' => $job->id]);
            \App\Models\User\UserJobPositions::create(['user_id' => $u->id, 'job_position_id' => $job->id, 'start_date' => now()->subYear(), 'status' => 'active']);
        }

        $this->employeeIn($this->unitA, 'Rekan Unit Atasan');

        // Atasan -> cuma bawahan langsung, rekan di unit yang sama tidak ikut jadi calon.
        $this->assertSame(['Perawat Bawahan'], app(AttendaceReqService::class)->unitMates($boss)->pluck('name')->all());

        $this->submit($boss, $staff->id);

        $this->assertSame([$staff->id], $this->substituteIds());
    }

    /** Hanya bawahan LANGSUNG - bawahan dari bawahan (beda unit) tidak boleh jadi calon pengganti. */
    public function test_indirect_subordinate_is_not_a_candidate(): void
    {
        $bossJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Karu', 'planned_manpower' => 1, 'org_structure_id' => $this->dept->id]);
        $midJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Katim', 'planned_manpower' => 1, 'org_structure_id' => $this->unitB->id, 'parent_id' => $bossJob->id]);
        $lowJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'org_structure_id' => $this->unitB->id, 'parent_id' => $midJob->id]);
        $users = [];
        foreach (['Atasan' => $bossJob, 'Bawahan Langsung' => $midJob, 'Bawahan Tidak Langsung' => $lowJob] as $name => $job) {
            $u = User::factory()->create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
            UserProfiles::create(['user_id' => $u->id, 'job_position_id' => $job->id]);
            \App\Models\User\UserJobPositions::create(['user_id' => $u->id, 'job_position_id' => $job->id, 'start_date' => now()->subYear(), 'status' => 'active']);
            $users[$name] = $u;
        }

        $names = app(AttendaceReqService::class)->unitMates($users['Atasan'])->pluck('name')->all();

        $this->assertContains('Bawahan Langsung', $names);
        $this->assertNotContains('Bawahan Tidak Langsung', $names);
    }

    /** Fitur cuma untuk 2 company RS - company lain: pilihan pengganti diabaikan, tidak tersimpan. */
    public function test_substitute_is_ignored_for_non_override_company(): void
    {
        $other = Companies::create(['code' => 'GEN', 'name' => 'Generik', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $other->id]);
        $flow = MasterApprovalFlows::create(['company_id' => $other->id, 'module' => 'attendance_request', 'name' => 'Alur', 'flow_type' => 'default', 'is_active' => true]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user',
            'approver_ref_id' => $approver->id, 'approval_mode' => 'any', 'scope_type' => 'global', 'is_final' => true,
        ]);
        $employee = User::factory()->create(['company_id' => $other->id]);
        $mate = User::factory()->create(['company_id' => $other->id]);

        $this->assertFalse(app(AttendaceReqService::class)->leaveSubstituteEnabled($employee));

        $this->submit($employee, $mate->id);

        $this->assertSame([], $this->substituteIds());
    }

    public function test_substitute_is_optional(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');

        $this->submit($employee, null);

        $this->assertSame([], $this->substituteIds());
    }

    private function holder(string $name, JobPositions $job): User
    {
        $u = User::factory()->create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
        UserProfiles::create(['user_id' => $u->id, 'job_position_id' => $job->id]);
        \App\Models\User\UserJobPositions::create(['user_id' => $u->id, 'job_position_id' => $job->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        return $u;
    }

    /** Pemohon di Sub Divisi: pengganti = semua orang di divisi induknya (kepala divisi, sub divisi lain), bukan divisi lain. */
    public function test_subdivision_applicant_gets_whole_division(): void
    {
        $sub = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Sub 1', 'level' => OrgStructurLevel::LEVEL_SUBDIVISION->value, 'parent_id' => $this->unitA->id]);
        $sub2 = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Sub 2', 'level' => OrgStructurLevel::LEVEL_SUBDIVISION->value, 'parent_id' => $this->unitA->id]);
        $mk = fn (OrgStructure $o, string $n) => $this->holder($n, JobPositions::create(['company_id' => $this->company->id, 'name' => "J {$n}", 'planned_manpower' => 1, 'org_structure_id' => $o->id]));

        $applicant = $mk($sub, 'Pemohon');
        $mk($sub, 'Rekan Sub');
        $mk($sub2, 'Orang Sub Lain');
        $mk($this->unitA, 'Kepala Divisi');
        $mk($this->unitB, 'Divisi Lain');

        $this->assertEqualsCanonicalizing(
            ['Rekan Sub', 'Orang Sub Lain', 'Kepala Divisi'],
            app(AttendaceReqService::class)->unitMates($applicant)->pluck('name')->all()
        );
    }

    /** Pemohon tanpa org_structure (Direktur): pengganti = bawahan langsung yang jabatannya level Departemen. */
    public function test_applicant_without_org_gets_direct_department_heads(): void
    {
        $dirJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Direktur', 'planned_manpower' => 1]);
        $kadepJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Kadep', 'planned_manpower' => 1, 'org_structure_id' => $this->dept->id, 'parent_id' => $dirJob->id]);
        $stafJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Staf', 'planned_manpower' => 1, 'org_structure_id' => $this->unitA->id, 'parent_id' => $dirJob->id]);

        $dir = $this->holder('Direktur', $dirJob);
        $this->holder('Kepala Departemen', $kadepJob);
        $this->holder('Staf Langsung', $stafJob);

        $this->assertSame(['Kepala Departemen'], app(AttendaceReqService::class)->unitMates($dir)->pluck('name')->all());
    }
}
