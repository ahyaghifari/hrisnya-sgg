<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\ShiftSwapHistorySheet;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Modal sheet riwayat tukar shift di widget Dashboard - versi ringkas dari MyShiftSwapRequests,
 * cuma dicek scoping ke requester_id sendiri (format baris sudah dicek lewat MyShiftSwapRequestsTest,
 * dibagi lewat trait FormatsShiftSwapRequest yang sama). */
class ShiftSwapHistorySheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_scoped_to_own_requester_id(): void
    {
        $company = Companies::create(['code' => 'SHT', 'name' => 'RS Sheet Test', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00']);

        $me = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);

        $myShift = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $me->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        $otherShift = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $other->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-11', 'off_day' => false]);

        $mine = ShiftSwapRequests::create(['company_id' => $company->id, 'requester_id' => $me->id, 'target_id' => $target->id, 'req_schedule_id' => $myShift->id, 'req_date' => '2026-08-10', 'target_date' => '2026-08-10', 'status' => 'in_review']);
        ShiftSwapRequests::create(['company_id' => $company->id, 'requester_id' => $other->id, 'target_id' => $target->id, 'req_schedule_id' => $otherShift->id, 'req_date' => '2026-08-11', 'target_date' => '2026-08-11', 'status' => 'in_review']);

        $component = Livewire::actingAs($me)->test(ShiftSwapHistorySheet::class)->call('openModal');

        $requests = $component->get('requests');

        $this->assertCount(1, $requests);
        $this->assertSame($mine->id, $requests->first()->id);
        $component->assertSet('showModal', true);
    }
}
