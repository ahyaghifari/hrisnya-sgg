<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\HolidaysPage;
use App\Models\Master\Attendance\Holidays;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HolidaysPage dibangun ulang dari reuse Filament (HolidaysTable/HolidayForm) ke custom
 * List.js + kalender bulan berjalan (2 kolom: tabel + kalender) — lihat komentar di
 * App\Livewire\Presensi\HolidaysPage.
 */
class HolidaysPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Holidays', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Holidays', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:Holidays', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Holidays Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Create:Holidays', 'Update:Holidays', 'Delete:Holidays']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->assertForbidden();
    }

    public function test_page_renders_and_shows_holiday_in_table_and_calendar(): void
    {
        $user = $this->actingAsAbsensi();
        $holiday = Holidays::create(['name' => 'Hari Kemerdekaan', 'date' => now()->startOfMonth()->addDays(4), 'is_collective_leave' => false]);

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->assertOk()
            ->assertSee('Hari Kemerdekaan');
    }

    public function test_creates_holiday(): void
    {
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->call('openCreateModal')
            ->set('formName', 'Tahun Baru')
            ->set('formDate', now()->addDays(10)->format('Y-m-d'))
            ->set('formIsCollectiveLeave', true)
            ->call('saveForm');

        $this->assertDatabaseHas('m_holidays', [
            'name' => 'Tahun Baru',
            'is_collective_leave' => true,
        ]);
    }

    public function test_updates_holiday(): void
    {
        $user = $this->actingAsAbsensi();
        $holiday = Holidays::create(['name' => 'Lama', 'date' => now(), 'is_collective_leave' => false]);

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->call('openEditModal', $holiday->id)
            ->set('formName', 'Baru')
            ->call('saveForm');

        $this->assertDatabaseHas('m_holidays', ['id' => $holiday->id, 'name' => 'Baru']);
    }

    public function test_deletes_holiday(): void
    {
        $user = $this->actingAsAbsensi();
        $holiday = Holidays::create(['name' => 'Dihapus', 'date' => now(), 'is_collective_leave' => false]);

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->call('removeHoliday', $holiday->id);

        $this->assertDatabaseMissing('m_holidays', ['id' => $holiday->id]);
    }

    public function test_calendar_navigates_to_next_month(): void
    {
        $user = $this->actingAsAbsensi();

        $component = Livewire::actingAs($user)
            ->test(HolidaysPage::class);

        $currentMonth = now()->format('Y-m');
        $nextMonth = now()->addMonthNoOverflow()->format('Y-m');

        $this->assertSame($currentMonth, $component->get('calendarMonth'));

        $component->call('nextMonth');

        $this->assertSame($nextMonth, $component->get('calendarMonth'));
    }
}
