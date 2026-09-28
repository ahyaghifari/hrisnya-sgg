<?php

namespace Tests\Feature\Attendance;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB - company-scoped, default OFF (Alasan/
 * Keterangan tetap opsional). Dicek di AttendaceReqService (bukan cuma rules() Livewire) karena
 * mobile API (AttendanceRequestController) submit langsung lewat service ini juga.
 */
class KeteranganCutiIzinWajibTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithApprovalFlow(bool $keteranganWajib): Companies
    {
        $company = Companies::create(['code' => 'KCI', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id, 'module' => 'attendance_request',
            'name' => 'Alur Test', 'flow_type' => 'default', 'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user',
            'approver_ref_id' => $approver->id, 'approval_mode' => 'any',
            'scope_type' => 'global', 'is_final' => true,
        ]);

        if ($keteranganWajib) {
            Setting::create([
                'company_id' => $company->id,
                'key' => AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB->value,
                'module' => 'attendance',
                'type' => 'boolean',
                'label' => AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB->label(),
                'value' => '1',
            ]);
        }

        return $company;
    }

    public function test_leave_without_reason_still_allowed_when_setting_off(): void
    {
        $company = $this->companyWithApprovalFlow(keteranganWajib: false);
        $employee = User::factory()->create(['company_id' => $company->id]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
        ], $employee);

        $this->assertDatabaseCount('attendance_requests', 1);
    }

    public function test_leave_without_reason_blocked_when_setting_on(): void
    {
        $company = $this->companyWithApprovalFlow(keteranganWajib: true);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->expectExceptionMessage('Alasan/Keterangan cuti wajib diisi.');

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
        ], $employee);
    }

    public function test_leave_with_reason_allowed_when_setting_on(): void
    {
        $company = $this->companyWithApprovalFlow(keteranganWajib: true);
        $employee = User::factory()->create(['company_id' => $company->id]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'start_date' => now()->addDays(31)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'reason' => 'Acara keluarga',
        ], $employee);

        $this->assertDatabaseCount('attendance_requests', 1);
    }

    public function test_permission_without_purpose_blocked_when_setting_on(): void
    {
        $company = $this->companyWithApprovalFlow(keteranganWajib: true);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->expectExceptionMessage('Keterangan izin wajib diisi.');

        app(AttendaceReqService::class)->storePermissionRequest([
            'type_id' => PermissionTypeEnum::MENIKAH_KARYAWAN->value,
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
        ], $employee);
    }

    public function test_permission_with_purpose_allowed_when_setting_on(): void
    {
        $company = $this->companyWithApprovalFlow(keteranganWajib: true);
        $employee = User::factory()->create(['company_id' => $company->id]);

        app(AttendaceReqService::class)->storePermissionRequest([
            'type_id' => PermissionTypeEnum::MENIKAH_KARYAWAN->value,
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'purpose' => 'Menikah',
        ], $employee);

        $this->assertDatabaseCount('attendance_requests', 1);
    }
}
