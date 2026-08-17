<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\JadwalSayaPage;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kalender pribadi "Jadwal Shift Saya" (Fase 1, planning tukar shift/extra shift untuk
 * Perawat/Kepala Ruangan) - cek gerbang User::hasShiftSchedule() (bukan permission lagi,
 * lihat catatan 2026-08-15) dan isi kalender per hari (shift terjadwal/libur/hari libur
 * nasional/tidak ada jadwal).
 */
class JadwalSayaPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithShiftSchedule(Companies $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);

        // hasShiftSchedule() cuma butuh 1 baris roster ada, tanggalnya tidak relevan buat gate.
        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => null,
            'date' => '2020-01-01',
            'off_day' => true,
        ]);

        return $user;
    }

    public function test_user_without_shift_schedule_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'JSW', 'name' => 'RS Test', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        Livewire::test(JadwalSayaPage::class)->assertForbidden();
    }

    public function test_calendar_shows_shift_off_day_and_national_holiday(): void
    {
        $company = Companies::create(['code' => 'JSW2', 'name' => 'RS Test 2', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithShiftSchedule($company);

        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Perawat',
            'schedule_type' => 'shift',
        ]);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
            'color' => '#22c55e',
        ]);

        // Perawat kerja Pagi tanggal 10, libur eksplisit tanggal 11, hari libur nasional
        // tanggal 17 (Kemerdekaan) - 3 baris ScheduleShiftAssignment, cuma 1 juga row Holidays.
        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $pagi->id,
            'date' => '2026-08-10',
            'off_day' => false,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => null,
            'date' => '2026-08-11',
            'off_day' => true,
        ]);

        Holidays::create([
            'name' => 'HUT RI',
            'date' => '2026-08-17',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(JadwalSayaPage::class, ['month' => '2026-08'])
            ->assertOk();

        $weeks = $component->get('weeks');
        $byDate = collect($weeks)->flatten(1)->keyBy(fn ($day) => $day['date']->toDateString());

        $this->assertSame('scheduled', $byDate['2026-08-10']['schedule']['state']);
        $this->assertSame('Pagi', $byDate['2026-08-10']['schedule']['label']);
        $this->assertSame('07:00–15:00', $byDate['2026-08-10']['schedule']['detail']);

        $this->assertSame('off', $byDate['2026-08-11']['schedule']['state']);

        $this->assertSame('holiday', $byDate['2026-08-17']['schedule']['state']);
        $this->assertSame('HUT RI', $byDate['2026-08-17']['schedule']['label']);

        // Tanggal tanpa roster sama sekali (belum di-assign) - bukan "off", murni "none".
        $this->assertSame('none', $byDate['2026-08-05']['schedule']['state']);
    }

    public function test_navigation_changes_month(): void
    {
        $company = Companies::create(['code' => 'JSW3', 'name' => 'RS Test 3', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithShiftSchedule($company);
        $this->actingAs($user);

        Livewire::test(JadwalSayaPage::class, ['month' => '2026-08'])
            ->call('nextMonth')
            ->assertSet('month', '2026-09')
            ->call('previousMonth')
            ->assertSet('month', '2026-08')
            ->call('previousMonth')
            ->assertSet('month', '2026-07');
    }
}
