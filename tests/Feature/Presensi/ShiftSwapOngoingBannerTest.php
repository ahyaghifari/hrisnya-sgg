<?php

namespace Tests\Feature\Presensi;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Livewire\Presensi\ShiftSwapOngoingBanner;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Banner "Tukar Shift Berjalan" - komponen terpisah dari ShiftSwapRequestModal (lihat
 * App\Livewire\Presensi\ShiftSwapOngoingBanner) supaya di desktop dia tidak kejepit dalam
 * flex-row tombol Tukar Shift/Riwayat. */
class ShiftSwapOngoingBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_nothing_when_no_ongoing_swap(): void
    {
        $company = Companies::create(['code' => 'BNR', 'name' => 'RS Banner Test', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(ShiftSwapOngoingBanner::class)
            ->assertDontSee('Tukar Shift Berjalan');
    }

    public function test_shows_own_in_review_swap_request(): void
    {
        $company = Companies::create(['code' => 'BNR2', 'name' => 'RS Banner Test 2', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        $head = User::factory()->create(['company_id' => $company->id]);
        $schedule->update(['head_user_id' => $head->id]);

        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);

        $reqSchedule = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $requester->id, 'shift_type_id' => $pagi->id, 'date' => today()->addDays(5), 'off_day' => false]);
        $targetSchedule = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $target->id, 'shift_type_id' => $pagi->id, 'date' => today()->addDays(6), 'off_day' => false]);

        $flow = MasterApprovalFlows::create(['company_id' => $company->id, 'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value, 'name' => 'Alur Test', 'flow_type' => 'default', 'is_active' => true]);
        MasterApprovalFlowSteps::create(['flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user', 'approver_ref_id' => $head->id, 'approval_mode' => 'any', 'scope_type' => 'global', 'is_final' => true]);

        ShiftSwapRequests::create([
            'company_id' => $company->id,
            'requester_id' => $requester->id,
            'target_id' => $target->id,
            'req_schedule_id' => $reqSchedule->id,
            'target_schedule_id' => $targetSchedule->id,
            'swap_kind' => 'schedule',
            'req_date' => $reqSchedule->date,
            'target_date' => $targetSchedule->date,
            'status' => 'in_review',
        ]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapOngoingBanner::class)
            ->assertSee('Tukar Shift Berjalan')
            ->assertSee($target->name);

        Livewire::actingAs($target)
            ->test(ShiftSwapOngoingBanner::class)
            ->assertDontSee('Tukar Shift Berjalan');
    }
}
