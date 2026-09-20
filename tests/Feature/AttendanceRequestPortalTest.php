<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Livewire\SubmitOvertimeRequest;
use App\Livewire\SubmitPermissionRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Models\Attendance\AttendanceRequests;
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
        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;

        $this->actingAs($user);

        $date = now()->addDay()->toDateString();

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $leaveType->value)
            ->set('start_date', $date)
            ->set('end_date', $date)
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'in_review',
        ]);

        // Jenis cuti tetap (enum): yang tersimpan label + key kategori, bukan ID master.
        $this->assertDatabaseHas('attendance_req_leave_details', ['leave_type' => 'Cuti Tahunan', 'category' => 'tahunan']);
    }

    public function test_leave_type_must_be_one_of_the_fixed_types(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user);

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', 'cuti_besar')
            ->set('start_date', now()->addDay()->toDateString())
            ->set('end_date', now()->addDay()->toDateString())
            ->call('submit')
            ->assertHasErrors(['leave_type']);

        $this->assertDatabaseMissing('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_submit_button_stays_locked_after_success_and_second_submit_is_ignored(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $this->actingAs($user);

        $date = now()->addDay()->toDateString();

        // Cuti
        $leave = Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', LeaveTypeCategoryEnum::TAHUNAN->value)
            ->set('start_date', $date)->set('end_date', $date)
            ->assertSet('submitted', false)
            ->assertSee('Ajukan Cuti')
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSee('Diajukan...')   // teks + ikon berputar, tombol mati sampai pindah halaman
            ->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->where('type', 'leave')->count());

        // Izin
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value)
            ->set('date', $date)->set('start_time', '08:00')->set('end_time', '09:00')
            ->call('submit')
            ->assertSet('submitted', true)
            ->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->where('type', 'permission')->count());

        // Lembur
        Livewire::test(SubmitOvertimeRequest::class)
            ->set('overtime_type', 'biasa')->set('date', $date)
            ->set('tasks', [['task_description' => 'x', 'start_time' => '18:00', 'end_time' => '20:00']])
            ->call('submit')
            ->assertSet('submitted', true)
            ->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->where('type', 'overtime')->count());
    }

    public function test_submit_button_is_not_locked_when_validation_fails(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $this->actingAs($user);

        Livewire::test(SubmitOvertimeRequest::class)
            ->call('submit') // jenis & tanggal kosong
            ->assertHasErrors(['overtime_type', 'date'])
            ->assertSet('submitted', false);
    }

    public function test_employee_can_submit_permission_request(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value)
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

    public function test_flat_group_stores_fixed_type_name_and_category_without_choosing_a_type(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', now()->toDateString())
            ->set('end_date', now()->toDateString())
            ->call('submit');

        $this->assertDatabaseHas('attendance_req_permission_details', [
            'permission_type' => 'Izin Sakit',
            'category' => 'izin_sakit',
        ]);
    }

    public function test_special_permission_stores_type_label_and_khusus_category(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('permission_type', PermissionTypeEnum::KELUARGA_INTI_MENINGGAL->value)
            ->set('date', now()->toDateString())
            ->call('submit');

        $this->assertDatabaseHas('attendance_req_permission_details', [
            'permission_type' => 'Izin Keluarga Inti Meninggal',
            'category' => 'izin_khusus',
        ]);
    }

    public function test_employee_can_submit_overtime_request(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);
        $this->actingAs($user);

        Livewire::test(SubmitOvertimeRequest::class)
            ->set('overtime_type', 'khusus')
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

        // Jenis lembur tetap (enum): yang tersimpan label-nya, bukan key form.
        $this->assertDatabaseHas('attendance_req_overtime_details', ['overtime_type' => 'Lembur Khusus/Proyek']);
    }

    public function test_overtime_form_shows_type_buttons_before_the_date_field(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user);

        Livewire::test(SubmitOvertimeRequest::class)
            ->assertSeeInOrder(['Jenis Lembur', 'Lembur Biasa', 'Lembur Khusus/Proyek', 'Tanggal Lembur'])
            ->call('$set', 'overtime_type', 'khusus')
            ->assertSet('overtime_type', 'khusus');
    }

    public function test_overtime_type_must_be_one_of_the_two_fixed_types(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = $this->createEmployee($company);

        $this->actingAs($user);

        Livewire::test(SubmitOvertimeRequest::class)
            ->set('overtime_type', 'malam')
            ->set('date', now()->toDateString())
            ->call('submit')
            ->assertHasErrors(['overtime_type']);

        $this->assertDatabaseMissing('attendance_requests', ['user_id' => $user->id, 'type' => 'overtime']);
    }
}
