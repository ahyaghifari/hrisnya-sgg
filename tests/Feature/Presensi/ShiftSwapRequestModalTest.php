<?php

namespace Tests\Feature\Presensi;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Livewire\Presensi\ShiftSwapRequestModal;
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

/**
 * Modal "Tukar Shift" di bawah widget Dashboard - submit lewat komponen Livewire, bukan halaman
 * terpisah (lihat planning/2026-08-16-fitur-tukar-shift.md §A). Alur sederhana tanpa "mode"
 * (dihapus 2026-08-16, dulu ada schedule/off_day yang bikin bingung): cari rekan -> pilih jadwal
 * rekan -> pilih jadwal sendiri penggantinya (bulan sama).
 */
class ShiftSwapRequestModalTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'MDL', 'name' => 'RS Modal Test', 'location' => 'Test', 'is_active' => true]);

        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);

        $this->pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => ShiftType::PAGI->value, 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);
        $this->siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => ShiftType::SIANG->value, 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false]);

        $approver = User::factory()->create(['company_id' => $this->company->id]);
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
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    public function test_submit_schedule_swap_creates_request_and_closes_modal(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Rekan Target']);

        $myShift = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-08-20', 'off_day' => false]);
        $targetShift = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => '2026-08-21', 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->call('selectTarget', $target->id)
            ->set('targetScheduleId', $targetShift->id)
            ->set('reqScheduleId', $myShift->id)
            ->call('submit')
            ->assertSet('showModal', false);

        $this->assertSame(1, ShiftSwapRequests::where('requester_id', $requester->id)->count());
        $swap = ShiftSwapRequests::where('requester_id', $requester->id)->first();
        $this->assertSame($target->id, $swap->target_id);
        $this->assertSame('in_review', $swap->status);
    }

    public function test_submit_without_own_schedule_fails_validation(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);
        $targetShift = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => '2026-08-21', 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->call('selectTarget', $target->id)
            ->set('targetScheduleId', $targetShift->id)
            ->call('submit')
            ->assertHasErrors(['reqScheduleId'])
            ->assertSet('showModal', true);

        $this->assertSame(0, ShiftSwapRequests::count());
    }

    public function test_my_shifts_scoped_to_same_month_as_target_shift(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);

        $myShiftSameMonth = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-08-05', 'off_day' => false]);
        $myShiftOtherMonth = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-09-05', 'off_day' => false]);
        $targetShift = ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => '2026-08-21', 'off_day' => false]);

        $component = Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('selectTarget', $target->id)
            ->set('targetScheduleId', $targetShift->id);

        $myShifts = $component->get('myShifts');

        $this->assertTrue($myShifts->contains('id', $myShiftSameMonth->id));
        $this->assertFalse($myShifts->contains('id', $myShiftOtherMonth->id));
    }

    public function test_target_candidates_search_scoped_to_same_schedule_and_excludes_self(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Requester']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-08-05', 'off_day' => false]);

        $sameSchedule = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Rekan Sejadwal']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $sameSchedule->id, 'shift_type_id' => $this->siang->id, 'date' => '2026-08-06', 'off_day' => false]);

        $otherSchedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Lain', 'schedule_type' => 'shift']);
        $otherShiftType = ScheduleShiftType::create(['schedule_id' => $otherSchedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => ShiftType::MALAM->value, 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'is_overnight' => true]);
        $beda = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Jadwal Lain']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $beda->id, 'shift_type_id' => $otherShiftType->id, 'date' => '2026-08-06', 'off_day' => false]);

        $tanpaJadwal = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Tanpa Jadwal']);

        $component = Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->set('targetSearch', 'Budi');

        $candidates = $component->get('targetCandidates');

        $this->assertTrue($candidates->contains('id', $sameSchedule->id));
        $this->assertFalse($candidates->contains('id', $requester->id));
        $this->assertFalse($candidates->contains('id', $beda->id), 'Jadwal shift beda tidak boleh muncul.');
        $this->assertFalse($candidates->contains('id', $tanpaJadwal->id), 'Tanpa jadwal shift tidak boleh muncul.');
    }

    public function test_target_candidates_empty_when_requester_has_no_shift_schedule(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Tanpa Jadwal']);
        $someone = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Lainnya']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $someone->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-08-06', 'off_day' => false]);

        $component = Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->set('targetSearch', 'Budi');

        $this->assertCount(0, $component->get('targetCandidates'));
    }
}
