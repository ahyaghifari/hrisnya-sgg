<?php

namespace Tests\Feature\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * "Tanggapan Rekan" opsional - TIDAK PERNAH menggerbang alur approval, lihat
 * planning/2026-08-16-fitur-tukar-shift.md §A2.
 */
class ShiftSwapTargetResponseTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $requester;
    private User $target;
    private User $approver;
    private ScheduleShiftAssignment $scheduleA;
    private ScheduleShiftAssignment $scheduleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TRS', 'name' => 'RS Target Response Test', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false]);

        $this->requester = User::factory()->create(['company_id' => $this->company->id]);
        $this->target = User::factory()->create(['company_id' => $this->company->id]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Tukar Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $this->scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-20', 'off_day' => false]);
        $this->scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-21', 'off_day' => false]);
    }

    private function submit(): \App\Models\Shift\ShiftSwapRequests
    {
        Auth::login($this->requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $this->scheduleA->id,
            'target_schedule_id' => $this->scheduleB->id,
        ]);
        Auth::logout();

        return $swap;
    }

    public function test_setting_off_by_default_snapshot_created_immediately(): void
    {
        $swap = $this->submit();

        $this->assertNotNull($swap->fresh()->approvalSnapshot);
        $this->assertNull($swap->target_response);
    }

    public function test_setting_on_snapshot_still_created_immediately_not_gated(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'module' => 'attendance',
            'key' => ShiftSwapRequestService::ASK_TARGET_RESPONSE_SETTING_KEY,
            'type' => 'boolean',
            'value' => '1',
            'label' => 'Minta Tanggapan Rekan buat Tukar Shift',
        ]);

        $swap = $this->submit();

        // Approval snapshot tetap langsung dibuat, TIDAK menunggu tanggapan target sama sekali.
        $this->assertNotNull($swap->fresh()->approvalSnapshot);
    }

    public function test_record_target_response_does_not_touch_snapshot_or_status(): void
    {
        $swap = $this->submit();
        $statusBefore = $swap->status;
        $snapshotIdBefore = $swap->approvalSnapshot->id;

        app(ShiftSwapRequestService::class)->recordTargetResponse($swap, $this->target, 'accepted');

        $swap->refresh();
        $this->assertSame('accepted', $swap->target_response);
        $this->assertNotNull($swap->target_responded_at);
        $this->assertSame($statusBefore, $swap->status);
        $this->assertSame($snapshotIdBefore, $swap->fresh()->approvalSnapshot->id);
    }

    public function test_approver_can_approve_regardless_of_target_response(): void
    {
        $swap = $this->submit();
        // Target belum menanggapi sama sekali (masih null).

        app(ApprovalFlowService::class)->advanceStep(
            $swap->approvalSnapshot,
            $this->approver->id,
            'Disetujui',
            'approved',
        );

        $this->assertSame('approved', $swap->fresh()->status);
    }

    public function test_non_target_user_cannot_record_response(): void
    {
        $swap = $this->submit();
        $stranger = User::factory()->create(['company_id' => $this->company->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ShiftSwapRequestService::class)->recordTargetResponse($swap, $stranger, 'accepted');
    }
}
