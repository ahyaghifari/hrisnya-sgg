<?php

namespace Tests\Feature;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Livewire\PendingApprovalsCard;
use App\Livewire\Presensi\ShiftSwapApprovalsCard;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integrasi modul shift_swap_request ke widget "Butuh Persetujuan Anda" (2026-08-16) - lihat
 * planning/2026-08-16-fitur-tukar-shift.md §C. Fokus cuma modul baru ini, bukan cakupan penuh
 * PendingApprovalsCard (belum ada test file existing buat komponen ini).
 */
class PendingApprovalsCardShiftSwapTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_shift_swap_request_shows_up_and_can_be_approved(): void
    {
        $company = Companies::create(['code' => 'PAC', 'name' => 'RS PendingApprovals Test', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false]);

        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);

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

        // Rekan (target) sekarang jadi approver PERTAMA sungguhan (2026-08-20), tapi widget-nya
        // KHUSUS (ShiftSwapApprovalsCard) - TIDAK dobel muncul di "Butuh Persetujuan Anda"
        // (instruksi user: itu murni buat approval berbasis manajemen/atasan).
        Livewire::actingAs($approver)->test(PendingApprovalsCard::class)->assertDontSee('Tukar Shift');
        Livewire::actingAs($target)->test(PendingApprovalsCard::class)->assertDontSee('Tukar Shift');

        $targetComponent = Livewire::actingAs($target)->test(ShiftSwapApprovalsCard::class);
        $targetComponent->assertSee($requester->name);
        $targetComponent->call('approve', $swap->id);

        $this->assertSame('in_review', $swap->fresh()->status, 'Belum final, masih nunggu approver master flow.');

        $component = Livewire::actingAs($approver)->test(PendingApprovalsCard::class);

        $component->assertSee('Tukar Shift');

        $component->set('selected', ['shift_swap_request:' . $swap->id])
            ->call('confirmBulkApprove');

        $this->assertSame('approved', $swap->fresh()->status);
    }
}
