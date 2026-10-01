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

        $this->unitA = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Unit A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $this->unitB = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Unit B', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
    }

    private function employeeIn(OrgStructure $unit, string $name): User
    {
        $job = JobPositions::create(['company_id' => $this->company->id, 'name' => "Jabatan {$name}", 'planned_manpower' => 1, 'org_structure_id' => $unit->id]);
        $user = User::factory()->create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'job_position_id' => $job->id]);

        return $user;
    }

    private function submit(User $employee, ?string $substituteId): void
    {
        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'substitute_user_id' => $substituteId,
        ], $employee);
    }

    public function test_substitute_must_be_in_same_unit_and_is_stored(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');
        $mate = $this->employeeIn($this->unitA, 'Rekan Unit');
        $this->employeeIn($this->unitB, 'Orang Lain');

        $options = app(AttendaceReqService::class)->unitMates($employee)->pluck('name')->all();
        $this->assertSame(['Rekan Unit'], $options);

        $this->submit($employee, $mate->id);

        $this->assertSame($mate->id, AttendanceRequestLeaveDetails::first()->substitute_user_id);
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
        $bossJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Karu', 'planned_manpower' => 1, 'org_structure_id' => $this->unitA->id]);
        $staffJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'org_structure_id' => $this->unitB->id, 'parent_id' => $bossJob->id]);
        $boss = User::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Perawat Bawahan', 'is_active' => true]);
        foreach ([[$boss, $bossJob], [$staff, $staffJob]] as [$u, $job]) {
            UserProfiles::create(['user_id' => $u->id, 'job_position_id' => $job->id]);
            \App\Models\User\UserJobPositions::create(['user_id' => $u->id, 'job_position_id' => $job->id, 'start_date' => now()->subYear(), 'status' => 'active']);
        }

        $this->assertContains('Perawat Bawahan', app(AttendaceReqService::class)->unitMates($boss)->pluck('name')->all());

        $this->submit($boss, $staff->id);

        $this->assertSame($staff->id, AttendanceRequestLeaveDetails::first()->substitute_user_id);
    }

    /** Hanya bawahan LANGSUNG - bawahan dari bawahan (beda unit) tidak boleh jadi calon pengganti. */
    public function test_indirect_subordinate_is_not_a_candidate(): void
    {
        $bossJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Karu', 'planned_manpower' => 1, 'org_structure_id' => $this->unitA->id]);
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

        $this->assertNull(AttendanceRequestLeaveDetails::first()->substitute_user_id);
    }

    public function test_substitute_is_optional(): void
    {
        $employee = $this->employeeIn($this->unitA, 'Pemohon');

        $this->submit($employee, null);

        $this->assertNull(AttendanceRequestLeaveDetails::first()->substitute_user_id);
    }
}
