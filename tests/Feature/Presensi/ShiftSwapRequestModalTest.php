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
 * terpisah. Alur (2026-08-20): tanggal shift SENDIRI dulu (default besok, tidak boleh kurang dari
 * hari ini) -> mode tanggal sama/beda (kalau beda, isi tanggal shift rekan dulu) -> baru cari
 * rekan (TomSelect searchable, opsinya sudah difilter ke yang punya shift di tanggal EFEKTIF itu)
 * - lihat ShiftSwapRequestModal.php.
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

        // Kepala Ruangan jadwal wajib ada, kalau null store() diblokir
        // (ShiftSwapRequestService::createApprovalSnapshot()).
        $schedule->update(['head_user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

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

    public function test_submit_same_date_swap_creates_request_and_closes_modal(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Rekan Target']);

        $date = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $date, 'off_day' => false]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => $date, 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->set('reqDate', $date)
            ->set('targetUserId', $target->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertSame(1, ShiftSwapRequests::where('requester_id', $requester->id)->count());
        $swap = ShiftSwapRequests::where('requester_id', $requester->id)->first();
        $this->assertSame($target->id, $swap->target_id);
        $this->assertSame('in_review', $swap->status);
    }

    public function test_submit_different_date_swap_resolves_target_shift_at_its_own_date(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);

        $reqDate = now()->addDay()->toDateString();
        $targetDate = now()->addDays(3)->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => $targetDate, 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->set('reqDate', $reqDate)
            ->set('dateMode', 'different')
            ->set('targetDate', $targetDate)
            ->set('targetUserId', $target->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $swap = ShiftSwapRequests::where('requester_id', $requester->id)->first();
        $this->assertSame($targetDate, $swap->target_date->toDateString());
    }

    public function test_submit_reciprocal_off_swap_creates_request_with_swap_kind(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);

        $reqDate = now()->addDay()->toDateString();
        $compensationDate = now()->addDays(5)->toDateString();

        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);
        // Target LIBUR BENERAN (baris ada, off_day=true) di reqDate.
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => null, 'date' => $reqDate, 'off_day' => true]);
        // Requester juga WAJIB Libur beneran di tanggal kompensasi (instruksi user 2026-09-14).
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => null, 'date' => $compensationDate, 'off_day' => true]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->pagi->id, 'date' => $compensationDate, 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->set('reqDate', $reqDate)
            ->set('dateMode', 'reciprocal')
            ->set('targetUserId', $target->id)
            ->set('targetDate', $compensationDate)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $swap = ShiftSwapRequests::where('requester_id', $requester->id)->first();
        $this->assertNotNull($swap);
        $this->assertSame('reciprocal_off', $swap->swap_kind);
        $this->assertSame($compensationDate, $swap->target_date->toDateString());
    }

    public function test_off_day_candidates_exclude_na_and_require_real_off_day_row(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $reqDate = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);

        // Rekan A: Libur BENERAN di reqDate - harus muncul.
        $reallyOff = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $reallyOff->id, 'shift_type_id' => null, 'date' => $reqDate, 'off_day' => true]);
        // Perlu punya jejak sejadwal (baris kerja lain) supaya lolos filter "sesama jadwal".
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $reallyOff->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDays(5)->toDateString(), 'off_day' => false]);

        // Rekan B: N/A/kosong (tidak ada baris sama sekali) di reqDate - TIDAK boleh muncul.
        $empty = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $empty->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDays(5)->toDateString(), 'off_day' => false]);

        $component = Livewire::actingAs($requester)->test(ShiftSwapRequestModal::class)
            ->set('reqDate', $reqDate)
            ->set('dateMode', 'reciprocal');

        $candidates = $component->get('offDayCandidates');

        $this->assertTrue($candidates->contains('user_id', $reallyOff->id));
        $this->assertFalse($candidates->contains('user_id', $empty->id), 'N/A/kosong tidak boleh dianggap Libur.');
    }

    public function test_submit_without_own_schedule_fails_validation(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $target->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->set('targetUserId', $target->id)
            ->call('submit')
            ->assertHasErrors(['reqDate'])
            ->assertSet('showModal', true);

        $this->assertSame(0, ShiftSwapRequests::count());
    }

    public function test_submit_without_target_schedule_fails_validation(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $target = User::factory()->create(['company_id' => $this->company->id]);
        $reqDate = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);

        Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->call('openModal')
            ->set('reqDate', $reqDate)
            ->set('targetUserId', $target->id)
            ->call('submit')
            ->assertHasErrors(['targetDate'])
            ->assertSet('showModal', true);

        $this->assertSame(0, ShiftSwapRequests::count());
    }

    public function test_target_candidates_scoped_to_same_schedule_and_shift_on_req_date(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Requester']);
        $date = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $date, 'off_day' => false]);

        // Rekan sejadwal, punya shift di tanggal yang SAMA (reqDate default besok) - harus muncul.
        $sameSchedule = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Rekan Sejadwal']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $sameSchedule->id, 'shift_type_id' => $this->siang->id, 'date' => $date, 'off_day' => false]);

        // Sejadwal tapi shift-nya BEDA TANGGAL (bukan reqDate) - tidak boleh muncul di langkah ini.
        $bedaTanggal = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Beda Tanggal']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $bedaTanggal->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDays(3)->toDateString(), 'off_day' => false]);

        $otherSchedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Jadwal Lain', 'schedule_type' => 'shift']);
        $otherShiftType = ScheduleShiftType::create(['schedule_id' => $otherSchedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => ShiftType::MALAM->value, 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'is_overnight' => true]);
        $beda = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Jadwal Lain']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $beda->id, 'shift_type_id' => $otherShiftType->id, 'date' => $date, 'off_day' => false]);

        $component = Livewire::actingAs($requester)->test(ShiftSwapRequestModal::class)->set('reqDate', $date);

        $candidates = $component->get('targetCandidates');

        $this->assertTrue($candidates->contains('user_id', $sameSchedule->id));
        $this->assertFalse($candidates->contains('user_id', $requester->id));
        $this->assertFalse($candidates->contains('user_id', $bedaTanggal->id), 'Shift di tanggal lain tidak boleh muncul.');
        $this->assertFalse($candidates->contains('user_id', $beda->id), 'Jadwal shift beda tidak boleh muncul.');
    }

    public function test_target_candidates_empty_when_requester_has_no_shift_schedule(): void
    {
        $requester = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Tanpa Jadwal']);
        $someone = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Lainnya']);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $someone->id, 'shift_type_id' => $this->pagi->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        $component = Livewire::actingAs($requester)->test(ShiftSwapRequestModal::class);

        $this->assertCount(0, $component->get('targetCandidates'));
    }

    public function test_off_day_candidates_narrowed_to_own_team_when_schedule_uses_teams(): void
    {
        $schedule = Schedule::where('company_id', $this->company->id)->firstOrFail();
        $schedule->update(['use_teams' => true]);

        $teamA = \App\Models\Shift\ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $teamB = \App\Models\Shift\ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu B']);

        $requester = User::factory()->create(['company_id' => $this->company->id]);
        $reqDate = now()->addDay()->toDateString();
        $period = now()->addDay()->format('Y-m');
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $requester->id, 'shift_type_id' => $this->pagi->id, 'date' => $reqDate, 'off_day' => false]);
        \App\Models\Shift\ScheduleShiftTeamMember::setForPeriod($schedule, $requester, $teamA, $period, $requester->id);

        // Rekan SATU regu (A), Libur beneran di reqDate - harus tetap muncul.
        $teammate = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $teammate->id, 'shift_type_id' => null, 'date' => $reqDate, 'off_day' => true]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $teammate->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDays(5)->toDateString(), 'off_day' => false]);
        \App\Models\Shift\ScheduleShiftTeamMember::setForPeriod($schedule, $teammate, $teamA, $period, $requester->id);

        // Rekan BEDA regu (B), Libur beneran di reqDate juga - HARUS tersaring, bukan regu sendiri.
        $otherTeam = User::factory()->create(['company_id' => $this->company->id]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $otherTeam->id, 'shift_type_id' => null, 'date' => $reqDate, 'off_day' => true]);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $otherTeam->id, 'shift_type_id' => $this->siang->id, 'date' => now()->addDays(5)->toDateString(), 'off_day' => false]);
        \App\Models\Shift\ScheduleShiftTeamMember::setForPeriod($schedule, $otherTeam, $teamB, $period, $requester->id);

        $candidates = Livewire::actingAs($requester)
            ->test(ShiftSwapRequestModal::class)
            ->set('reqDate', $reqDate)
            ->set('dateMode', 'reciprocal')
            ->get('offDayCandidates');

        $this->assertTrue($candidates->contains('user_id', $teammate->id));
        $this->assertFalse($candidates->contains('user_id', $otherTeam->id), 'Rekan beda regu tidak boleh muncul kalau schedule pakai Regu.');
    }
}
