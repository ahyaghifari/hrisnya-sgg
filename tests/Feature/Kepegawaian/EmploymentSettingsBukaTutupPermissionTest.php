<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Livewire\Kepegawaian\EmploymentSettingsPage;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pengaturan Kepegawaian: "Mode Buka/Tutup" select (masa_kerja/buka_tutup) diatur dari sini,
 * cuma pemegang EDIT_PENGATURAN_KEPEGAWAIAN yang boleh ubah. PENGANGKATAN_DIBUKA (status
 * buka/tutup) sengaja TIDAK ada di halaman ini lagi - toggle-nya di Dashboard Sikartap
 * (izin BUKA_TUTUP_PENGAJUAN_KARTAP), lihat DashboardSikartapBukaTutupKartapTest.
 */
class EmploymentSettingsBukaTutupPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'EST', 'name' => 'Settings Co', 'location' => 'Test', 'is_active' => true]);
    }

    private function userWith(CustomPermissionEnum ...$permissions): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::create(['name' => 'Role ' . uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
            $role->givePermissionTo($permission->value);
        }

        $user->assignRole($role);

        return $user;
    }

    private function stored(KepegawaianSettingEnum $case): ?string
    {
        return Setting::where('company_id', $this->company->id)->where('key', $case->value)->value('value');
    }

    public function test_edit_holder_can_change_mode_via_select(): void
    {
        $user = $this->userWith(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN, CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN);
        $this->actingAs($user);

        Livewire::test(EmploymentSettingsPage::class)
            ->assertSet('canEditSettings', true)
            ->assertSet('values.' . KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value, 'masa_kerja')
            ->set('values.' . KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value, 'buka_tutup')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('buka_tutup', $this->stored(KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP));
    }

    public function test_view_only_user_cannot_save(): void
    {
        $user = $this->userWith(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN);
        $this->actingAs($user);

        Livewire::test(EmploymentSettingsPage::class)
            ->assertSet('canEditSettings', false)
            ->set('values.' . KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value, 'buka_tutup')
            ->call('save')
            ->assertForbidden();

        $this->assertNull($this->stored(KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP));
    }

    /** BUKA_TUTUP_PENGAJUAN_KARTAP tidak lagi relevan buat halaman ini - cuma modal buat
     * toggle di Dashboard Sikartap sekarang, jadi holder murni permission ini tanpa LIHAT
     * tetap 403 di sini. */
    public function test_buka_tutup_permission_alone_still_cannot_open_employment_settings(): void
    {
        $user = $this->userWith(CustomPermissionEnum::BUKA_TUTUP_PENGAJUAN_KARTAP);
        $this->actingAs($user);

        Livewire::test(EmploymentSettingsPage::class)->assertForbidden();
    }

    /** PENGANGKATAN_DIBUKA tidak boleh dirender/ditulis dari halaman ini sama sekali, walau
     * user punya EDIT_PENGATURAN_KEPEGAWAIAN (payload dimanipulasi memasukkan key itu). */
    public function test_dibuka_key_is_never_written_from_employment_settings_even_by_edit_holder(): void
    {
        $user = $this->userWith(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN, CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN);
        $this->actingAs($user);

        Livewire::test(EmploymentSettingsPage::class)
            ->assertSet('settings', fn ($cases) => ! in_array(KepegawaianSettingEnum::PENGANGKATAN_DIBUKA, $cases, true))
            ->set('values.' . KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->value, '1')
            ->call('save');

        $this->assertNull($this->stored(KepegawaianSettingEnum::PENGANGKATAN_DIBUKA));
    }
}
