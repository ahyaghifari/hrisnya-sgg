<?php

namespace Tests\Feature\Presensi;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftSwapRequestDetailTest extends TestCase
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

    public function test_unrelated_user_is_forbidden(): void
    {
        $stranger = User::factory()->create(['company_id' => $this->company->id]);

        Livewire::actingAs($stranger)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertForbidden();
    }

    public function test_requester_can_view(): void
    {
        Livewire::actingAs($this->requester)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertOk();
    }

    public function test_target_can_view(): void
    {
        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertOk();
    }

    /** Rekan (target) sekarang jadi approver PERTAMA sungguhan (2026-08-20) - approver master
     * flow belum bisa apa-apa sebelum rekan setuju duluan. */
    public function test_target_approves_first_then_approver_approves_and_schedules_are_swapped(): void
    {
        Livewire::actingAs($this->approver)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertSet('canApprove', false);

        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertSet('canApprove', true)
            ->call('chooseAction', 'approve')
            ->set('note', 'Rekan setuju')
            ->call('confirmAction');

        $this->assertSame('in_review', $this->swapRequest->fresh()->status, 'Belum final, masih nunggu approver master flow.');
        $this->assertSame('accepted', $this->swapRequest->fresh()->target_response, 'Rekan approve step-nya -> "Tanggapan Rekan" ikut terisi.');

        $this->swapRequest->refresh();
        Livewire::actingAs($this->approver)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->assertSet('canApprove', true)
            ->call('chooseAction', 'approve')
            ->set('note', 'Disetujui')
            ->call('confirmAction');

        $this->assertSame('approved', $this->swapRequest->fresh()->status);
    }

    public function test_target_rejects_marks_status_rejected_immediately(): void
    {
        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->call('chooseAction', 'reject')
            ->set('note', 'Rekan tidak setuju')
            ->call('confirmAction');

        $this->assertSame('rejected', $this->swapRequest->fresh()->status);
        $this->assertSame('declined', $this->swapRequest->fresh()->target_response);
    }

    public function test_approver_rejects_marks_status_rejected(): void
    {
        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->call('chooseAction', 'approve')
            ->set('note', 'Rekan setuju')
            ->call('confirmAction');

        $this->swapRequest->refresh();
        Livewire::actingAs($this->approver)
            ->test(ShiftSwapRequestDetail::class, ['record' => $this->swapRequest])
            ->call('chooseAction', 'reject')
            ->set('note', 'Tidak disetujui')
            ->call('confirmAction');

        $this->assertSame('rejected', $this->swapRequest->fresh()->status);
    }

    public function test_riwayat_page_is_gated_by_shift_schedule_only(): void
    {
        $this->actingAs($this->requester)->get(route('tukar-shift'))->assertOk();

        $noShift = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($noShift)->get(route('tukar-shift'))->assertForbidden();
    }

    public function test_route_accessible_via_http_without_any_permission(): void
    {
        $this->actingAs($this->requester)
            ->get(route('tukar-shift.show', ['record' => $this->swapRequest]))
            ->assertOk();
    }
}
