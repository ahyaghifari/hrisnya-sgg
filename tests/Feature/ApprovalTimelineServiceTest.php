<?php

namespace Tests\Feature;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Approval\ApprovalTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug nyata: build() crash ("Attempt to read property name on null") kalau user pengaju sudah
 * di-soft-delete - attendance_requests.user_id pakai cascadeOnDelete() (baris DB tetap ada
 * selama hard-delete tidak terjadi), tapi relasi user() Eloquent default TIDAK ikut baris
 * soft-deleted, jadi $request->user null walau baris user-nya masih ada di DB.
 */
class ApprovalTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_does_not_crash_when_requester_is_soft_deleted(): void
    {
        $company = Companies::create(['code' => 'ATS', 'name' => 'Company ATS', 'location' => 'Test', 'is_active' => true]);
        $requester = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Resign']);

        $request = AttendanceRequests::create([
            'user_id' => $requester->id,
            'type' => 'leave',
            'status' => 'submitted',
        ]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'name' => 'Alur Test',
            'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        ApprovalSnapshots::create([
            'module_type' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            'module_id' => $request->id,
            'flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'company_id' => $company->id,
            'created_by' => $requester->id,
            'current_step_order' => 1,
            'status' => 'in_review',
            'steps_snapshot' => [
                ['order' => 1, 'approver_type' => 'user', 'approver_ref_id' => $requester->id],
            ],
        ]);

        $requester->delete();

        $timeline = app(ApprovalTimelineService::class)->build($request->fresh());

        $this->assertSame('Seseorang', $timeline[0]['actor']);
        $this->assertSame('done', $timeline[0]['state']);
    }
}
