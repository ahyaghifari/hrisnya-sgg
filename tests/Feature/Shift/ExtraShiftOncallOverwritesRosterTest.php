<?php

namespace Tests\Feature\Shift;

use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oncall BUKAN Extra Shift (revisi 2026-09-14, lihat
 * planning/2026-09-14-oncall-middle-redesign.md) - desain awal 2026-09-13 (syncOncallRoster()/
 * revertOncallRoster()) sudah DIHAPUS. Sekarang Oncall shift roster BIASA, assignable langsung
 * ke schedule_shift_assignments (sama seperti Pagi/Siang/Malam/Middle), TIDAK pernah lewat
 * ExtraShiftAssignment lagi. Extra shift biasa (pagi->siang dst) TETAP additive, tidak boleh
 * ikut kesentuh - dicek di test terakhir (tidak berubah dari sebelumnya).
 */
class ExtraShiftOncallOverwritesRosterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(Companies $company): Schedule
    {
        return Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
    }

    public function test_oncall_is_assignable_directly_to_roster_like_any_other_shift_type(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP001', 'company_id' => $company->id]);
        $schedule = $this->makeSchedule($company);
        $oncall = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'oncall',
            'code' => 'OC',
            'name' => 'Oncall',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'counted_work_days' => 1,
        ]);

        // Oncall diisi LANGSUNG ke roster, sama seperti shift lain - bukan lewat ExtraShiftAssignment.
        $roster = ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $oncall->id,
            'date' => '2026-08-10',
            'off_day' => false,
        ]);

        $this->assertFalse($roster->fresh()->off_day);
        $this->assertSame($oncall->id, $roster->fresh()->shift_type_id);

        // Oncall tidak dikecualikan dari dropdown roster/rotasi/regu lagi.
        $this->assertTrue($schedule->shiftTypes->contains('id', $oncall->id));
    }

    public function test_creating_extra_shift_assignment_with_oncall_type_does_not_touch_roster(): void
    {
        $company = Companies::create(['code' => 'C3', 'name' => 'Company 3', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP003', 'company_id' => $company->id]);
        $schedule = $this->makeSchedule($company);
        $oncall = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'oncall',
            'code' => 'OC',
            'name' => 'Oncall',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'counted_work_days' => 1,
        ]);

        // Baris ExtraShiftAssignment shift_type=oncall SEHARUSNYA tidak pernah dibuat lagi lewat
        // UI manapun (ExtraShiftRulePage sudah tidak menawarkan Oncall), tapi kalau toh ada
        // (data lama/manual), booted() hook yang dulu nyinkron ke roster sudah dihapus - tidak
        // boleh ada efek samping apa pun ke schedule_shift_assignments.
        ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $oncall->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $user->id,
        ]);

        $this->assertNull(ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', '2026-08-10')->first());
        $this->assertDatabaseMissing('schedule_shift_roster_logs', ['user_id' => $user->id]);
    }

    public function test_extra_shift_rule_options_only_allow_the_3_main_shift_categories(): void
    {
        $options = app(\App\Livewire\Presensi\ExtraShiftRulePage::class)->shiftTypeOptions;

        $this->assertFalse(collect($options)->contains(\App\Enum\ShiftType::ONCALL));
        $this->assertSame(
            [\App\Enum\ShiftType::PAGI, \App\Enum\ShiftType::SIANG, \App\Enum\ShiftType::MALAM],
            $options
        );
    }

    public function test_normal_extra_shift_stays_additive_and_does_not_touch_roster(): void
    {
        $company = Companies::create(['code' => 'C2', 'name' => 'Company 2', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP002', 'company_id' => $company->id]);
        $schedule = $this->makeSchedule($company);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'pagi',
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '14:00:00',
            'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'siang',
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00:00',
            'end_time' => '21:00:00',
            'counted_work_days' => 1,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $pagi->id,
            'date' => '2026-08-10',
            'off_day' => false,
        ]);

        ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $siang->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $user->id,
        ]);

        // Roster tidak berubah - masih Pagi, bukan ketiban Siang.
        $roster = ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', '2026-08-10')->first();
        $this->assertSame($pagi->id, $roster->shift_type_id);
        $this->assertDatabaseMissing('schedule_shift_roster_logs', ['user_id' => $user->id]);
    }
}
