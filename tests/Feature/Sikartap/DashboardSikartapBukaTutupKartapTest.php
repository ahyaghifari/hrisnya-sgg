<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Livewire\Sikartap\DashboardSikartapPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Widget "Buka/Tutup Pengajuan Karyawan Tetap" di Dashboard Sikartap (dipindah dari Pengaturan
 * Kepegawaian, instruksi user 2026-09-27) - butuh 1 company spesifik terpilih + izin
 * BUKA_TUTUP_PENGAJUAN_KARTAP. Efeknya diverifikasi lewat User::isEligibleForPengangkatanTetap().
 */
class DashboardSikartapBukaTutupKartapTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'DSK', 'name' => 'Dashboard Co', 'location' => 'Test', 'is_active' => true]);
    }

    private function actingAsHolder(): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::firstOrCreate(['name' => 'Buka Tutup Kartap Holder'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::BUKA_TUTUP_PENGAJUAN_KARTAP->value, 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function setMode(string $mode): void
    {
        Setting::updateOrCreate(
            ['company_id' => $this->company->id, 'key' => KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value],
            ['module' => 'kepegawaian', 'type' => 'string', 'label' => 'x', 'value' => $mode],
        );
    }

    public function test_widget_hidden_without_permission(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::firstOrCreate(['name' => 'No Buka Tutup'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value, 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->assertSet('canBukaTutupKartap', false);
    }

    /** "Semua perusahaan" (companyId null) cuma bisa dipilih user yang canFilterByCompany() -
     * butuh LIHAT_PERUSAHAAN_LAIN, beda dari test lain di sini yang company-bound biasa (companyId
     * selalu ke-force ke company sendiri, tidak pernah null). */
    public function test_widget_hidden_when_no_specific_company_selected(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::firstOrCreate(['name' => 'Buka Tutup Holder Lintas Company'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::BUKA_TUTUP_PENGAJUAN_KARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        // mount() sudah set companyId=null buat user canFilterByCompany() (lihat "Semua
        // perusahaan" di dropdown) - tidak perlu di-set manual.
        Livewire::test(DashboardSikartapPage::class)
            ->assertSet('companyId', null)
            ->assertSet('canBukaTutupKartap', false);
    }

    /** Mode default (masa_kerja, tanpa Setting sama sekali) - widget toggle tidak boleh muncul
     * sama sekali, walau user punya izin BUKA_TUTUP_PENGAJUAN_KARTAP dan company sudah dipilih.
     * "Buka/tutup" tidak relevan buat mode ini (instruksi user 2026-09-27). */
    public function test_widget_hidden_when_mode_is_masa_kerja(): void
    {
        $this->actingAsHolder();
        // Sengaja TIDAK memanggil setMode() - default company belum punya Setting mode sama sekali.

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->assertSet('canBukaTutupKartap', true)
            ->assertSet('pengangkatanMode', 'masa_kerja')
            ->assertSet('showBukaTutupToggle', false);
    }

    public function test_widget_shown_only_when_mode_is_buka_tutup(): void
    {
        $this->actingAsHolder();

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->assertSet('showBukaTutupToggle', false);

        $this->setMode('buka_tutup');

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->assertSet('showBukaTutupToggle', true);
    }

    public function test_holder_can_toggle_dibuka_and_it_flips_eligibility(): void
    {
        $this->actingAsHolder();
        $this->setMode('buka_tutup');

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->assertSet('canBukaTutupKartap', true)
            ->assertSet('pengangkatanDibuka', false)
            ->call('toggleKartapDibuka')
            ->assertSet('pengangkatanDibuka', true);

        $this->assertSame(
            '1',
            Setting::where('company_id', $this->company->id)->where('key', KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->value)->value('value'),
        );

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->call('toggleKartapDibuka')
            ->assertSet('pengangkatanDibuka', false);
    }

    public function test_non_holder_cannot_toggle_even_by_calling_method_directly(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::firstOrCreate(['name' => 'No Buka Tutup 2'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value, 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(DashboardSikartapPage::class)
            ->set('companyId', $this->company->id)
            ->call('toggleKartapDibuka')
            ->assertForbidden();
    }
}
