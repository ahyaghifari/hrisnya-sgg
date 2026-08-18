<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\TeamShiftScheduleCard;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widget "Jadwal Shift Tim" di Dashboard - atasan lihat jadwal bawahannya per hari (beda dari
 * widget "Jadwal Shift Saya" yang isinya jadwal sendiri). Scope pakai
 * ResolvesShiftTeamMembers::subordinatesWithSharedSchedule() - bawahan struktur jabatan + siapa
 * pun yang pakai Schedule sama (kasus "Perawat Orientasi" - jabatan generik company-wide, bukan
 * anak struktur Kepala Ruangan manapun, tapi roster shift-nya nunjuk ke Schedule unit itu),
 * minus diri sendiri. Semua bawahan tampil apa adanya (tidak difilter hasShiftSchedule() lagi,
 * instruksi user 2026-08-18: karyawan tanpa jadwal apa pun tetap harus kelihatan dengan status
 * "Belum ada jadwal").
 */
class TeamShiftScheduleCardTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    public function test_shows_all_subordinates_regardless_of_shift_schedule(): void
    {
        $company = Companies::create(['code' => 'TSC', 'name' => 'RS Team Shift Card Test', 'location' => 'Test', 'is_active' => true]);

        // Schedule unit Saniah - dipakai bawahan sah DAN karyawan "orientasi" yang jabatannya
        // sendiri tidak nyambung ke struktur Saniah sama sekali.
        $unitASchedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat Unit A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $unitASchedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false, 'color' => '#22c55e']);

        // Schedule unit LAIN - dipakai outsider, harus TETAP tidak ikut kehitung walau
        // outsider sama-sama berjadwal shift (schedule-nya beda, bukan cuma struktur jabatannya).
        $otherSchedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat Unit B', 'schedule_type' => 'shift']);
        $pagiUnitB = ScheduleShiftType::create(['schedule_id' => $otherSchedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        $kepalaPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 2, 'parent_id' => $kepalaPosition->id]);

        $kepala = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($kepala, $kepalaPosition);

        $bawahan = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Satu']);
        $this->assignJobPosition($bawahan, $staffPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $bawahan->id, 'shift_type_id' => $pagi->id, 'date' => now()->toDateString(), 'off_day' => false]);

        // Bawahan tanpa jadwal apa pun (mis. baru masuk/orientasi) - HARUS tetap tampil, bukan
        // disembunyikan, dengan status "Belum ada jadwal" di sisi tampilan.
        $bawahanTanpaJadwal = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Tanpa Jadwal']);
        $this->assignJobPosition($bawahanTanpaJadwal, $staffPosition);

        // Kasus nyata (NIP 02022026834): "Perawat Orientasi" - jabatan GENERIK, bukan anak
        // struktur Kepala Ruangan manapun (tidak di-assignJobPosition ke $staffPosition), tapi
        // roster shift-nya nunjuk ke Schedule unit yang SAMA dengan bawahan sah ($pagi). Harus
        // ikut tampil walau bukan bawahan formal.
        $orientasiSchedulSama = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Orientasi Unit A']);
        $orientasiPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat Orientasi', 'planned_manpower' => 5]);
        $this->assignJobPosition($orientasiSchedulSama, $orientasiPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $orientasiSchedulSama->id, 'shift_type_id' => $pagi->id, 'date' => now()->toDateString(), 'off_day' => false]);

        $outsider = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Unit Lain']);
        $otherPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan Lain', 'planned_manpower' => 1]);
        $this->assignJobPosition($outsider, $otherPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $outsider->id, 'shift_type_id' => $pagiUnitB->id, 'date' => now()->toDateString(), 'off_day' => false]);

        $component = Livewire::actingAs($kepala)->test(TeamShiftScheduleCard::class);

        $names = $component->get('teamMembers')->pluck('name')->all();

        $this->assertContains($bawahan->name, $names);
        $this->assertContains($bawahanTanpaJadwal->name, $names);
        $this->assertContains($orientasiSchedulSama->name, $names); // Bukan bawahan formal, tapi 1 schedule.
        $this->assertNotContains($kepala->name, $names); // Widget ini bukan buat diri sendiri.
        $this->assertNotContains($outsider->name, $names); // Schedule beda (unit lain).

        $schedule = collect($component->get('teamSchedule'))->firstWhere('user.id', $bawahan->id);
        $this->assertSame('scheduled', $schedule['schedule']['state']);
        $this->assertSame('Pagi', $schedule['schedule']['label']);

        $noneSchedule = collect($component->get('teamSchedule'))->firstWhere('user.id', $bawahanTanpaJadwal->id);
        $this->assertSame('none', $noneSchedule['schedule']['state']);

        // Link "Detail Jadwal" nunjuk ke Schedule unit A (dipakai tim), walau Kepala Ruangan
        // sendiri tidak punya roster shift apa pun di skenario ini.
        $this->assertSame($unitASchedule->id, $component->get('teamScheduleId'));
    }

    public function test_navigating_day_shows_holiday(): void
    {
        $company = Companies::create(['code' => 'TSC2', 'name' => 'RS Team Shift Card Test 2', 'location' => 'Test', 'is_active' => true]);

        $kepalaPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'parent_id' => $kepalaPosition->id]);

        $kepala = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($kepala, $kepalaPosition);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false]);

        $bawahan = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat Satu']);
        $this->assignJobPosition($bawahan, $staffPosition);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $bawahan->id, 'shift_type_id' => $pagi->id, 'date' => now()->addDay()->toDateString(), 'off_day' => false]);

        Holidays::create(['name' => 'Libur Nasional Test', 'date' => now()->addDay()->toDateString()]);

        $component = Livewire::actingAs($kepala)->test(TeamShiftScheduleCard::class)
            ->call('nextDay');

        $schedule = collect($component->get('teamSchedule'))->firstWhere('user.id', $bawahan->id);
        $this->assertSame('holiday', $schedule['schedule']['state']);
    }

    /** Regresi: blade-nya sempat membungkus SELURUH root <div> di dalam @if teamMembers -
     * begitu tim kosong, tidak ada elemen HTML yang di-render sama sekali, Livewire melempar
     * RootTagMissingFromViewException. Root <div> sekarang selalu ada, isinya yang kondisional. */
    public function test_renders_without_error_when_user_has_no_subordinates(): void
    {
        $company = Companies::create(['code' => 'TSC3', 'name' => 'RS Team Shift Card Test 3', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)->test(TeamShiftScheduleCard::class)->assertOk();
    }

    /** Tombol "Extra Shift" dipindah ke sini (2026-08-18) dari widget "Jadwal Shift Saya" -
     * gate-nya cuma permission Create:ExtraShiftAssignment, TIDAK bergantung ke hasShiftSchedule()
     * atasan sendiri (beda dari sebelumnya, lihat catatan di TeamShiftScheduleCard). */
    public function test_can_create_extra_shift_property_reflects_permission(): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'Create:ExtraShiftAssignment', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Kepala Ruangan Extra Shift Gate Test', 'guard_name' => 'web']);
        $role->givePermissionTo('Create:ExtraShiftAssignment');

        $company = Companies::create(['code' => 'TSC4', 'name' => 'RS Team Shift Card Test 4', 'location' => 'Test', 'is_active' => true]);

        $withPermission = User::factory()->create(['company_id' => $company->id]);
        $withPermission->assignRole($role);

        $withoutPermission = User::factory()->create(['company_id' => $company->id]);

        $this->assertTrue(Livewire::actingAs($withPermission)->test(TeamShiftScheduleCard::class)->get('canCreateExtraShift'));
        $this->assertFalse(Livewire::actingAs($withoutPermission)->test(TeamShiftScheduleCard::class)->get('canCreateExtraShift'));
    }

    /** Regresi: waktu perbaikan RootTagMissingFromViewException, style grid-column:1/-1 sempat
     * ketinggal di <div> BAGIAN DALAM (bukan root Livewire), jadi kartu ini jadi cuma selebar 1
     * kolom bento di desktop (harusnya full-width) - lihat Livewire root wrapper yang jadi grid
     * item beneran di parent .bento, bukan div di dalamnya. */
    public function test_root_element_spans_full_grid_column(): void
    {
        $company = Companies::create(['code' => 'TSC5', 'name' => 'RS Team Shift Card Test 5', 'location' => 'Test', 'is_active' => true]);

        $kepalaPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Ruangan', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'parent_id' => $kepalaPosition->id]);

        $kepala = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($kepala, $kepalaPosition);

        $bawahan = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($bawahan, $staffPosition);

        $html = Livewire::actingAs($kepala)->test(TeamShiftScheduleCard::class)->html();

        // Root elemen (paling luar, dirender Livewire) yang harus bawa grid-column:1/-1, bukan
        // sekadar ADA di suatu tempat di HTML.
        $this->assertStringContainsString('grid-column:1/-1', trim(explode('>', $html, 2)[0]));
    }
}
