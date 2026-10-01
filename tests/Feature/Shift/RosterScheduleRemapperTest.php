<?php

namespace Tests\Feature\Shift;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Shift\RosterScheduleRemapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterScheduleRemapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_roster_rows_move_to_matching_shift_type_of_target_schedule(): void
    {
        $company = Companies::create(['code' => 'RMP', 'name' => 'Remap Co', 'location' => 'Test', 'is_active' => true]);
        $mk = fn (string $name, string $code) => ScheduleShiftType::create([
            'schedule_id' => Schedule::firstOrCreate(['company_id' => $company->id, 'name' => $name], ['schedule_type' => 'shift'])->id,
            'code' => $code, 'name' => ucfirst(strtolower($code)), 'shift_type' => 'pagi', 'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        $oldPagi = $mk('Unit A', 'PAGI');
        $oldSore = $mk('Unit A', 'SORE'); // tidak ada padanan di jadwal baru
        $newPagi = $mk('Unit B', 'PAGI');
        $user = User::factory()->create(['company_id' => $company->id]);

        $row = fn (string $date, $type) => ScheduleShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => $date,
        ]);
        $past = $row('2026-09-30', $oldPagi);
        $future = $row('2026-10-02', $oldPagi);
        $noMatch = $row('2026-10-03', $oldSore);

        $result = app(RosterScheduleRemapper::class)->remap($newPagi->schedule, [$user->id], '2026-10-01');

        $this->assertSame(['moved' => 1, 'unmapped' => 1], $result);
        $this->assertSame($oldPagi->id, $past->fresh()->shift_type_id);
        $this->assertSame($newPagi->id, $future->fresh()->shift_type_id);
        $this->assertSame($oldSore->id, $noMatch->fresh()->shift_type_id);
    }
}
