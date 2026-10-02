<?php

namespace Tests\Feature;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\AttendanceRequestDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi: getIzinPeriodeUsage() sekarang wajib PermissionGroupEnum sebagai argumen ke-2
 * (lihat App\Enum\Attendance\PermissionGroupEnum, restrukturisasi Izin 4 kelompok) - 2 call
 * site (AttendanceRequestDetail & AttendanceRequestForm/Filament) sempat kelewat diupdate,
 * bikin TypeError setiap kali buka detail pengajuan Izin. Test ini kunci supaya tidak kejadian
 * lagi.
 */
class AttendanceRequestDetailPermissionQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_permission_request_detail_does_not_throw(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
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

        app(AttendaceReqService::class)->storePermissionRequest([
            'type_id' => PermissionTypeEnum::TERLAMBAT->value,
            'date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'purpose' => 'Urusan keluarga',
        ], $employee);

        $request = AttendanceRequests::where('user_id', $employee->id)->firstOrFail();

        $this->actingAs($approver);

        $component = Livewire::test(AttendanceRequestDetail::class, ['record' => $request]);
        $quotaInfo = $component->get('quotaInfo');

        // used=0 karena request masih in_review (kuota hanya dihitung dari approved) -
        // fokus test ini memastikan tidak ada TypeError sampai struktur datanya lengkap kebaca.
        $this->assertSame('permission', $quotaInfo['kind']);
        $this->assertArrayHasKey('max', $quotaInfo['usage']);
        $this->assertSame(0, $quotaInfo['usage']['used']);
    }
}
