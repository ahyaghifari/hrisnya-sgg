<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Livewire\SubmitOvertimeRequest;
use App\Livewire\SubmitPermissionRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Attendance\OvertimeTypes;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceRequestPortalTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyWithApprovalFlow(): Companies
    {
        $company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);

        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'attendance_request',
            'name' => 'Alur Test',
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

        return $company;
    }

    private function createEmployee(Companies $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);

        // PENGAJUAN_ABSENSI_SAYA menggerbangi halaman submission sejak sesi row-1-nav — dibuat
        // eksplisit di sini (bukan diasumsikan default) karena role test ini dibuat mentah tanpa
        // permission apa pun.
        Permission::firstOrCreate(['name' => CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Employee Attendance Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value);
        $user->assignRole($role);

        return $user;
    }

    public function test_regular_employee_can_access_submission_pages(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user)->get(route('my-attendance-requests.leave'))->assertOk();
        $this->actingAs($user)->get(route('my-attendance-requests.permission'))->assertOk();
        $this->actingAs($user)->get(route('my-attendance-requests.overtime'))->assertOk();
        $this->actingAs($user)->get(route('my-attendance-requests'))->assertOk();
    }

    public function test_holding_director_is_forbidden_from_submission_pages(): void
    {
        Role::create(['name' => 'Direktur', 'guard_name' => 'web']);

        // ID holding company hardcoded di PermissionHelper::hasGlobalRole().
        // 'id' bukan mass-assignable (lihat Companies::$fillable), jadi diset manual sebelum save.
        $holdingId = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';
        $holding = new Companies();
        $holding->id = $holdingId;
        $holding->fill([
            'code' => 'HLD',
            'name' => 'Holding',
            'location' => 'Test',
            'is_active' => true,
        ]);
        $holding->save();

        $director = User::factory()->create(['company_id' => $holdingId]);
        $director->assignRole('Direktur');

        $this->actingAs($director)->get(route('my-attendance-requests.leave'))->assertForbidden();
        $this->actingAs($director)->get(route('my-attendance-requests.permission'))->assertForbidden();
        $this->actingAs($director)->get(route('my-attendance-requests.overtime'))->assertForbidden();
        $this->actingAs($director)->get(route('my-attendance-requests'))->assertForbidden();
    }

    public function test_employee_can_submit_leave_request(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $leaveType = LeaveTypes::create([
            'company_id' => $company->id,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', true)
            ->set('start_date', now()->addDay()->toDateString())
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'in_review',
        ]);
    }

    public function test_employee_can_submit_permission_request(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $permissionType = PermissionTypes::create([
            'company_id' => $company->id,
            'name' => 'Izin Umum',
        ]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type_id', $permissionType->id)
            ->set('date', now()->toDateString())
            ->set('start_time', '09:00')
            ->set('end_time', '11:00')
            ->set('purpose', 'Keperluan tes otomatis')
            ->set('location', 'Kantor')
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'type' => 'permission',
            'status' => 'in_review',
        ]);
    }

    public function test_employee_can_submit_overtime_request(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $overtimeType = OvertimeTypes::create([
            'company_id' => $company->id,
            'name' => 'Lembur Biasa',
        ]);

        $this->actingAs($user);

        Livewire::test(SubmitOvertimeRequest::class)
            ->set('overtime_type_id', $overtimeType->id)
            ->set('date', now()->toDateString())
            ->set('tasks', [
                ['task_description' => 'Menyelesaikan laporan', 'start_time' => '18:00', 'end_time' => '20:00'],
            ])
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'type' => 'overtime',
            'status' => 'in_review',
        ]);
    }
}
