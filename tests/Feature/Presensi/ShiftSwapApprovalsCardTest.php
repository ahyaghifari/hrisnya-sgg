<?php

namespace Tests\Feature\Presensi;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Livewire\Presensi\ShiftSwapApprovalsCard;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/** Widget KHUSUS rekan yang jadwalnya diminta ditukar - approver step 1 sungguhan (2026-08-20),
 * terpisah dari "Butuh Persetujuan Anda" (dicek di PendingApprovalsCardShiftSwapTest). */
class ShiftSwapApprovalsCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeSwapRequest(Companies $company, User $requester, User $target, User $approver): ShiftSwapRequests
    {
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00']);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00']);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Tukar Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $requester->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-20', 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $target->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-21', 'off_day' => false]);

        Auth::login($requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();

        return $swap;
    }

    public function test_reject_marks_status_rejected_immediately(): void
    {
        $company = Companies::create(['code' => 'SAC', 'name' => 'RS Approvals Card Test', 'location' => 'Test', 'is_active' => true]);
        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $swap = $this->makeSwapRequest($company, $requester, $target, $approver);

        Livewire::actingAs($target)
            ->test(ShiftSwapApprovalsCard::class)
            ->call('reject', $swap->id);

        $this->assertSame('rejected', $swap->fresh()->status);
    }

    public function test_only_target_can_act_on_the_request(): void
    {
        $company = Companies::create(['code' => 'SAC2', 'name' => 'RS Approvals Card Test 2', 'location' => 'Test', 'is_active' => true]);
        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);

        $swap = $this->makeSwapRequest($company, $requester, $target, $approver);

        Livewire::actingAs($stranger)
            ->test(ShiftSwapApprovalsCard::class)
            ->call('approve', $swap->id)
            ->assertForbidden();

        $this->assertSame('in_review', $swap->fresh()->status);
    }

    public function test_pending_scoped_to_own_target_step(): void
    {
        $company = Companies::create(['code' => 'SAC3', 'name' => 'RS Approvals Card Test 3', 'location' => 'Test', 'is_active' => true]);
        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $this->makeSwapRequest($company, $requester, $target, $approver);

        $this->assertCount(0, Livewire::actingAs($approver)->test(ShiftSwapApprovalsCard::class)->get('pending'));
        $this->assertCount(1, Livewire::actingAs($target)->test(ShiftSwapApprovalsCard::class)->get('pending'));
    }
}
