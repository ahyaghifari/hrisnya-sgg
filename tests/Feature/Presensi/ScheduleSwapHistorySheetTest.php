<?php

namespace Tests\Feature\Presensi;

use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleSwapHistorySheet;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleSwapHistorySheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_cell_lists_approved_swap_whose_row_moved_to_partner(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $rekan = User::factory()->create(['company_id' => $company->id]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);

        $type = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        // Baris sudah pindah pemilik ke rekan setelah approved.
        $row = ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $rekan->id, 'shift_type_id' => $type->id, 'date' => '2026-10-03', 'off_day' => false]);

        ShiftSwapRequests::create([
            'company_id' => $company->id,
            'requester_id' => $user->id,
            'target_id' => $rekan->id,
            'req_schedule_id' => $row->id,
            'req_date' => '2026-10-03',
            'target_date' => '2026-10-07',
            'status' => 'approved',
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleSwapHistorySheet::class, ['scheduleId' => $schedule->id])
            ->call('openModal', $user->id, '2026-10-03')
            ->assertSet('requests', fn ($r) => $r->count() === 1);
    }
}
