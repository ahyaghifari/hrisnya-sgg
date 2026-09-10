<?php

namespace Tests\Feature;

use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApprovalSubModuleTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $employee;

    /** @var array<string, User> ember => approver final */
    private array $approvers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create(['company_id' => $this->company->id]);

        // null = fallback semua jenis, + 1 alur khusus, + 1 alur multi-ember.
        $this->makeFlow(null, 'A');
        $this->makeFlow('meninggalkan_jam_kerja', 'B');
        $this->makeFlow('leave,tidak_masuk_seharian', 'C');
    }

    private function makeFlow(?string $subModule, string $key): void
    {
        $approver = User::factory()->create(['company_id' => $this->company->id]);
        $this->approvers[$key] = $approver;

        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'attendance_request',
            'sub_module' => $subModule,
            'name' => "Alur {$key}",
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
    }

    private function snapshotFlowIdFor(string $requestId): string
    {
        return ApprovalSnapshots::where('module_id', $requestId)->value('flow_id');
    }

    private function submitLeave(): AttendanceRequests
    {
        $leaveType = LeaveTypes::create([
            'company_id' => $this->company->id,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        app(AttendaceReqService::class)->storeLeaveRequest([
            'type_id' => $leaveType->id,
            'is_one_day' => true,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ], $this->employee);

        return AttendanceRequests::where('user_id', $this->employee->id)->where('type', 'leave')->latest('id')->firstOrFail();
    }

    private function submitPermission(string $category): AttendanceRequests
    {
        app(AttendaceReqService::class)->storePermissionRequest([
            'category' => $category,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ], $this->employee);

        return AttendanceRequests::where('user_id', $this->employee->id)->where('type', 'permission')->latest('id')->firstOrFail();
    }

    public function test_leave_routes_to_multi_bucket_flow(): void
    {
        $flowId = $this->snapshotFlowIdFor($this->submitLeave()->id);

        $this->assertSame(
            MasterApprovalFlows::where('name', 'Alur C')->value('id'),
            $flowId
        );
    }

    public function test_izin_tidak_masuk_shares_multi_bucket_flow_with_leave(): void
    {
        $flowId = $this->snapshotFlowIdFor($this->submitPermission('izin_seharian')->id);

        $this->assertSame(MasterApprovalFlows::where('name', 'Alur C')->value('id'), $flowId);
    }

    public function test_izin_meninggalkan_jam_kerja_routes_to_its_own_flow(): void
    {
        $flowId = $this->snapshotFlowIdFor($this->submitPermission('izin_terlambat_masuk')->id);

        $this->assertSame(MasterApprovalFlows::where('name', 'Alur B')->value('id'), $flowId);
    }

    public function test_unclaimed_bucket_falls_back_to_null_flow(): void
    {
        // Tidak ada alur yang klaim ember "sakit" → jatuh ke alur sub_module NULL (A).
        $flowId = $this->snapshotFlowIdFor($this->submitPermission('izin_sakit')->id);

        $this->assertSame(MasterApprovalFlows::where('name', 'Alur A')->value('id'), $flowId);
    }

    public function test_other_module_ignores_sub_module_param(): void
    {
        $trainingFlow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'training_request',
            'sub_module' => null,
            'name' => 'Alur Training',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        $resolved = app(ApprovalSnapshotRepository::class)->resolveApprovalFlowForUser(
            'training_request',
            $this->company->id,
            $this->employee->id,
        );

        $this->assertSame($trainingFlow->id, $resolved?->id);
    }
}
