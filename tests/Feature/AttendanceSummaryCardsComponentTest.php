<?php

namespace Tests\Feature;

use App\Livewire\AttendanceStatCards;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Komponen bersama Beranda & tab Absensi Profil Saya: Sisa Cuti Tahunan + Jumlah Izin + Jumlah Lembur (+ Extra Shift untuk karyawan berjadwal Shift). */
class AttendanceSummaryCardsComponentTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    /** User berjadwal Shift + 1 shift type (dipakai buat extra shift). */
    private function shiftUser(): array
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $type = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        ScheduleShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => now()->toDateString(),
        ]);

        return [$user, $type];
    }

    private function extraShift(User $user, ScheduleShiftType $type, string $date, string $category, string $status = 'approved'): void
    {
        ExtraShiftAssignment::create([
            'company_id' => $user->company_id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => $date,
            'status' => $status, 'source' => 'assigned', 'category' => $category, 'created_by' => $user->id,
        ]);
    }

    public function test_component_renders_the_summary_cards_for_self_and_for_another_user(): void
    {
        $viewer = User::factory()->create(['company_id' => $this->company->id]);
        $other = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($viewer);

        foreach (['<x-attendance-summary-cards />', '<x-attendance-summary-cards :compact="true" />', '<x-attendance-summary-cards :user-id="$id" />'] as $markup) {
            $this->blade($markup, ['id' => $other->id])
                ->assertSee('Cuti Tahunan')
                ->assertSee('Izin')
                ->assertSee('Lembur');
        }
    }

    public function test_labels_say_periode_ini_not_bulan_ini(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        Livewire::test(AttendanceStatCards::class)
            ->assertSee('pada periode ini')
            ->assertDontSee('bulan ini');
    }

    public function test_izin_and_lembur_are_counted_per_attendance_period_not_calendar_month(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => 'approved']);
        AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => 'approved']);
        AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => 'approved']);

        // Dibuat 3 bulan lalu - di luar periode ini, tidak ikut dihitung.
        $old = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => 'approved']);
        $old->forceFill(['created_at' => now()->subMonths(3)])->save();

        $page = Livewire::test(AttendanceStatCards::class);

        $this->assertSame(1, $page->viewData('izinCount'));
        $this->assertSame(2, $page->viewData('lemburCount'));
    }

    public function test_extra_shift_card_is_hidden_for_user_without_shift_schedule(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        Livewire::test(AttendanceStatCards::class)
            ->assertSet('userId', null)
            ->assertDontSee('Extra Shift')
            ->assertDontSee('extra periode ini');
    }

    public function test_extra_shift_card_shows_combined_total_with_extra_and_double_breakdown(): void
    {
        [$user, $type] = $this->shiftUser();
        $this->actingAs($user);

        $today = now()->toDateString();
        $this->extraShift($user, $type, $today, ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT);
        $this->extraShift($user, $type, $today, ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT);
        $this->extraShift($user, $type, $today, ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT);
        $this->extraShift($user, $type, $today, ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT, status: 'pending');           // belum disetujui
        $this->extraShift($user, $type, now()->subMonths(3)->toDateString(), ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT); // di luar periode

        $page = Livewire::test(AttendanceStatCards::class)
            ->assertSee('Extra Shift')
            ->assertSee('extra periode ini')
            ->assertSee('Rincian Periode Ini')
            ->assertSee('Double Shift');

        $this->assertSame(['extra' => 2, 'double' => 1, 'total' => 3], $page->viewData('extraShift'));
    }

    public function test_extra_shift_card_shows_data_of_the_profile_being_viewed(): void
    {
        [$staff, $type] = $this->shiftUser();
        $viewer = User::factory()->create(['company_id' => $this->company->id]); // tanpa jadwal shift
        $this->extraShift($staff, $type, now()->toDateString(), ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT);

        $this->actingAs($viewer);

        $page = Livewire::test(AttendanceStatCards::class, ['userId' => $staff->id]);

        $this->assertSame(1, $page->viewData('extraShift')['total']);
    }
}
