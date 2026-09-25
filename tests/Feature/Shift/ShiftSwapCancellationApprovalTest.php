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
use App\Services\Approval\ApprovalFlowService;
use App\Services\Shift\ShiftSwapCancellationService;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembatalan tukar shift yang SUDAH approved - lewat modul approval terpisah
 * (SHIFT_SWAP_REQUEST_CANCELLATION). Approve final -> shift_swap_requests jadi cancelled DAN
 * jadwal kedua pihak dikembalikan seperti sebelum tukar (revertApprovedSwap() baca execution_log).
 */
class ShiftSwapCancellationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $requester;
    private User $target;
    private User $approver;
    private User $cancellationApprover;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'SWC', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal', 'schedule_type' => 'shift']);
        $this->pagi = ScheduleShiftType::create(['schedule_id' => $this->schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);
        $this->siang = ScheduleShiftType::create(['schedule_id' => $this->schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false]);

        $this->requester = User::factory()->create(['company_id' => $this->company->id]);
        $this->target = User::factory()->create(['company_id' => $this->company->id]);
        $this->approver = User::factory()->create(['company_id' => $this->company->id]);
        $this->cancellationApprover = User::factory()->create(['company_id' => $this->company->id]);

        $this->schedule->update(['head_user_id' => $this->requester->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Tukar Shift',
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

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SHIFT_SWAP_REQUEST_CANCELLATION->value,
                'name' => 'Alur Pembatalan Tukar Shift',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->cancellationApprover->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    /** Approve tukar shift sampai selesai (target lalu approver) - reuse Livewire persis alur
     * approval asli yang sudah ada, biar executeApprovedSwap() beneran jalan lewat jalur nyata. */
    private function approveSwapFully(ShiftSwapRequests $swap): ShiftSwapRequests
    {
        Livewire::actingAs($this->target)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->call('chooseAction', 'approve')
            ->set('note', 'Rekan setuju')
            ->call('confirmAction');

        $swap->refresh();

        Livewire::actingAs($this->approver)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->call('chooseAction', 'approve')
            ->set('note', 'Disetujui')
            ->call('confirmAction');

        return $swap->fresh();
    }

    public function test_approving_cancellation_reverts_same_date_swap(): void
    {
        $date = now()->addMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString();

        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $date, 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $this->siang->id, 'date' => $date, 'off_day' => false]);

        Auth::login($this->requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();

        $swap = $this->approveSwapFully($swap);

        $this->assertSame($this->siang->id, $scheduleA->fresh()->shift_type_id, 'Sudah ketukar.');
        $this->assertSame($this->pagi->id, $scheduleB->fresh()->shift_type_id);
        $this->assertNotEmpty($swap->execution_log);

        $cancellation = app(ShiftSwapCancellationService::class)->requestCancellation($swap, $this->requester, 'Batal, ada kendala');

        Livewire::actingAs($this->cancellationApprover)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->assertSet('canApproveCancellation', true)
            ->call('chooseCancellationAction', 'approve')
            ->set('cancellationNote', 'Disetujui, silakan batal')
            ->call('confirmCancellationAction');

        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('shift_swap_request_cancellations', ['id' => $cancellation->id, 'status' => 'approved']);
        $this->assertSame($this->pagi->id, $scheduleA->fresh()->shift_type_id, 'Balik ke shift asal.');
        $this->assertSame($this->siang->id, $scheduleB->fresh()->shift_type_id);
        $this->assertNull($swap->fresh()->execution_log);
    }

    public function test_approving_cancellation_reverts_different_date_move(): void
    {
        $anchor = now()->addMonthNoOverflow()->startOfMonth();
        $reqDate = $anchor->copy()->addDays(5)->toDateString();
        $targetDate = $anchor->copy()->addDays(6)->toDateString();

        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $this->siang->id, 'date' => $targetDate, 'off_day' => false]);

        Auth::login($this->requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();

        $swap = $this->approveSwapFully($swap);

        $this->assertSame($this->target->id, $scheduleA->fresh()->user_id, 'Baris req_date pindah kepemilikan ke rekan.');
        $this->assertSame($this->requester->id, $scheduleB->fresh()->user_id, 'Baris target_date pindah kepemilikan ke pemohon.');

        app(ShiftSwapCancellationService::class)->requestCancellation($swap, $this->requester, 'Batal');

        Livewire::actingAs($this->cancellationApprover)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->call('chooseCancellationAction', 'approve')
            ->set('cancellationNote', 'Oke batal')
            ->call('confirmCancellationAction');

        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'cancelled']);
        $this->assertSame($this->requester->id, $scheduleA->fresh()->user_id, 'Balik ke pemilik asal.');
        $this->assertSame($this->target->id, $scheduleB->fresh()->user_id);
    }

    public function test_rejecting_cancellation_keeps_swap_approved(): void
    {
        $date = now()->addMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString();

        $scheduleA = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $date, 'off_day' => false]);
        $scheduleB = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->target->id, 'shift_type_id' => $this->siang->id, 'date' => $date, 'off_day' => false]);

        Auth::login($this->requester);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
        Auth::logout();

        $swap = $this->approveSwapFully($swap);

        $cancellation = app(ShiftSwapCancellationService::class)->requestCancellation($swap, $this->requester, 'Coba batal');

        Livewire::actingAs($this->cancellationApprover)
            ->test(ShiftSwapRequestDetail::class, ['record' => $swap])
            ->call('chooseCancellationAction', 'reject')
            ->set('cancellationNote', 'Tidak bisa, sudah dijalani')
            ->call('confirmCancellationAction');

        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'approved']);
        $this->assertDatabaseHas('shift_swap_request_cancellations', ['id' => $cancellation->id, 'status' => 'rejected']);
        $this->assertSame($this->siang->id, $scheduleA->fresh()->shift_type_id, 'Tetap hasil tukar, tidak direvert.');
    }
}
