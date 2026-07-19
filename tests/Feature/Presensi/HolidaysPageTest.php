<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\HolidaysPage;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
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
        $role = Role::firstOrCreate(['name' => 'Absensi Holidays Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

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
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->call('openCreateModal')
            ->set('formName', 'Tahun Baru')
            ->set('formDate', now()->addDays(10)->format('Y-m-d'))
            ->set('formCompanyId', $company->id)
            ->set('formIsCollectiveLeave', true)
            ->call('saveForm');

        $this->assertDatabaseHas('m_holidays', [
            'name' => 'Tahun Baru',
            'company_id' => $company->id,
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

    public function test_company_filter_shows_global_and_company_specific_only(): void
    {
        $user = $this->actingAsAbsensi();
        $companyA = Companies::create(['code' => 'AAA', 'name' => 'Company A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'BBB', 'name' => 'Company B', 'location' => 'Test', 'is_active' => true]);

        Holidays::create(['name' => 'Global Holiday', 'date' => now(), 'is_collective_leave' => false]);
        Holidays::create(['name' => 'Company A Only', 'date' => now(), 'company_id' => $companyA->id, 'is_collective_leave' => false]);
        Holidays::create(['name' => 'Company B Only', 'date' => now(), 'company_id' => $companyB->id, 'is_collective_leave' => false]);

        Livewire::actingAs($user)
            ->test(HolidaysPage::class)
            ->set('companyId', $companyA->id)
            ->assertSee('Global Holiday')
            ->assertSee('Company A Only')
            ->assertDontSee('Company B Only');
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
