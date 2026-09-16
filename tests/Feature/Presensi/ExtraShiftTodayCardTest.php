<?php

namespace Tests\Feature\Presensi;

use App\Livewire\ExtraShiftTodayCard;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kartu dashboard "Extra Shift/Double Shift Hari Ini" - beritahu jam scan yang perlu dilakukan,
 * beda pesan tergantung shift-nya nempel (2 scan) atau ada jeda/skip (wajib 4 scan) - lihat
 * ShiftAttendanceReader::isAdjacent().
 */
class ExtraShiftTodayCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(): array
    {
        $company = Companies::create(['code' => 'CARD', 'name' => 'Card Co', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang',
            'start_time' => '14:00:00', 'end_time' => '21:00:00', 'counted_work_days' => 1,
        ]);
        $malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => 'malam',
            'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_overnight' => true, 'counted_work_days' => 1,
        ]);

        return [$company, $schedule, $pagi, $siang, $malam];
    }

    public function test_no_card_when_no_extra_shift_today(): void
    {
        [$company, $schedule, $pagi] = $this->makeSchedule();
        $user = User::factory()->create(['company_id' => $company->id]);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => today(), 'off_day' => false]);

        Livewire::actingAs($user)
            ->test(ExtraShiftTodayCard::class)
            ->assertDontSeeHtml('extra-shift-card-inner');
    }

    public function test_adjacent_extra_shift_shows_two_scan_message(): void
    {
        [$company, $schedule, $pagi, $siang] = $this->makeSchedule();
        $user = User::factory()->create(['company_id' => $company->id]);

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => today(), 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => today(), 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(ExtraShiftTodayCard::class)
            ->assertSeeHtml('extra-shift-card-inner')
            ->assertSee('Extra Shift')
            ->assertSee('cukup scan', false)
            ->assertSee('07:00')
            ->assertSee('21:00');
    }

    public function test_skip_shift_extra_shows_four_scan_message(): void
    {
        [$company, $schedule, $pagi, , $malam] = $this->makeSchedule();
        $user = User::factory()->create(['company_id' => $company->id]);

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => today(), 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $malam->id, 'date' => today(), 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(ExtraShiftTodayCard::class)
            ->assertSeeHtml('extra-shift-card-inner')
            ->assertSee('WAJIB scan')
            ->assertSee('4x')
            ->assertSee('Masuk lagi', false);
    }

    public function test_double_shift_category_shows_double_shift_label(): void
    {
        [$company, $schedule, $pagi, $siang] = $this->makeSchedule();
        $user = User::factory()->create(['company_id' => $company->id]);

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => today(), 'off_day' => false]);
        ExtraShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => today(),
            'status' => 'approved', 'source' => 'swap_conflict', 'category' => ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT, 'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ExtraShiftTodayCard::class)
            ->assertSee('Double Shift')
            ->assertSee('akibat tukar shift', false);
    }

    public function test_extra_shift_on_libur_day_shows_extra_only_message(): void
    {
        [$company, $schedule, , $siang] = $this->makeSchedule();
        $user = User::factory()->create(['company_id' => $company->id]);

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => null, 'date' => today(), 'off_day' => true]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => today(), 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(ExtraShiftTodayCard::class)
            ->assertSee('libur', false)
            ->assertSee('tugas tambahan', false)
            ->assertSee('14:00');
    }
}
