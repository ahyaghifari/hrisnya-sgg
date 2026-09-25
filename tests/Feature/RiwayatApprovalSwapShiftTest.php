<?php

namespace Tests\Feature;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Enum\ShiftType;
use App\Livewire\RiwayatApprovalPage;
use App\Models\ApprovalManagement\ApprovalHistories;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiwayatApprovalSwapShiftTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $requester;
    private User $target;
    private User $approver;
    private ShiftSwapRequests $swapRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'DTL', 'name' => 'RS Detail Test', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false]);

        $this->requester = User::factory()->create(['company_id' => $this->company->id]);
        $this->target = User::factory()->create(['company_id' => $this->company->id]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

        // Kepala Ruangan jadwal wajib ada (kalau null store() diblokir). = requester biar step-nya
        // dilewati -> urutan approval tetap rekan -> master flow.
        $schedule->update(['head_user_id' => $this->requester->id]);

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

        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-20', 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-21', 'off_day' => false]);

        Auth::login($this->requester);
        $this->swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();
    }

    private function approvedByApprover(): void
    {
        ApprovalHistories::create([
            'action' => 'approved',
            'actor_id' => $this->approver->id,
            'actor_name' => $this->approver->name,
            'requestable_type' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
            'requestable_id' => $this->swapRequest->id,
        ]);
    }

    public function test_swap_shift_decision_listed_and_filterable(): void
    {
        $this->approvedByApprover();

        Livewire::actingAs($this->approver)
            ->test(RiwayatApprovalPage::class)
            ->assertSee('Tukar Shift')
            ->assertSee($this->requester->name)
            ->set('typeFilter', 'swap_shift')
            ->assertSee($this->requester->name)
            ->set('typeFilter', 'leave')
            ->assertDontSee($this->requester->name);
    }
}
