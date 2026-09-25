<?php

namespace Tests\Feature\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Livewire\Presensi\ShiftSwapRequestDetail;
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

/**
 * Pemohon asli (bukan rekan/approver) boleh membatalkan tukar shift miliknya sendiri selama
 * masih in_review dan belum lewat cutoff (tanggal paling awal dari req_date/target_date) - lihat
 * ShiftSwapRequestDetail::getCanSelfCancelProperty()/confirmSelfAction().
 */
class SelfCancelShiftSwapTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $requester;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'SSC', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        $this->requester = User::factory()->create(['company_id' => $this->company->id]);
        $this->target = User::factory()->create(['company_id' => $this->company->id]);
        $approver = User::factory()->create(['company_id' => $this->company->id]);

        $schedule->update(['head_user_id' => $this->requester->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Test',
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

        $this->pagi = $pagi;
        $this->schedule = $schedule;
    }

    private function submitSwap(string $reqDate, string $targetDate): ShiftSwapRequests
    {
        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $this->pagi->id, 'date' => $targetDate, 'off_day' => false]);

        Auth::login($this->requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();

        return $swap;
    }

    public function test_requester_can_self_cancel_while_in_review(): void
    {
        $anchor = now()->addMonthNoOverflow()->startOfMonth();
        $swap = $this->submitSwap($anchor->copy()->addDays(5)->toDateString(), $anchor->copy()->addDays(6)->toDateString());

        Livewire::actingAs($this->requester)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->assertSet('canSelfCancel', true)
            ->call('chooseSelfAction', 'self_cancel')
            ->set('selfActionReason', 'Batal, jadwal berubah')
            ->call('confirmSelfAction')
            ->assertSet('canSelfCancel', false);

        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'cancelled']);
    }

    public function test_self_cancel_blocked_after_cutoff(): void
    {
        $swap = $this->submitSwap(now()->subDays(2)->toDateString(), now()->subDay()->toDateString());

        Livewire::actingAs($this->requester)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->assertSet('canSelfCancel', false)
            ->call('chooseSelfAction', 'self_cancel')
            ->set('selfActionReason', 'Coba batal telat')
            ->call('confirmSelfAction')
            ->assertForbidden();

        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'in_review']);
    }

    public function test_target_cannot_self_cancel_requesters_swap(): void
    {
        $anchor = now()->addMonthNoOverflow()->startOfMonth();
        $swap = $this->submitSwap($anchor->copy()->addDays(5)->toDateString(), $anchor->copy()->addDays(6)->toDateString());

        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->assertSet('canSelfCancel', false)
            ->call('chooseSelfAction', 'self_cancel')
            ->set('selfActionReason', 'Coba batal punya orang')
            ->call('confirmSelfAction')
            ->assertForbidden();
    }
}
