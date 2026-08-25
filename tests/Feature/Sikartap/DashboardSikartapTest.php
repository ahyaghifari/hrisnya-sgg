<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Sikartap\DashboardSikartapPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Dashboard Sikartap widget-based, tiap widget punya permission sendiri (LIHAT_DASHBOARD_SIKARTAP_*). */
class DashboardSikartapTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    /** Gate masuk halaman = SISTEM_SIKARTAP (gerbang modul), widget permission cuma
     * mempengaruhi widget mana yang tampil - lihat DashboardSikartapPage::mount(). */
    private function actingAsWithPermission(CustomPermissionEnum $permission): User
    {
        $this->company ??= Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'Staff-' . $permission->value], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_blocked_without_sistem_sikartap_permission(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(DashboardSikartapPage::class)->assertForbidden();
    }

    public function test_statistik_widget_only_shows_with_permission(): void
    {
        $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_STATISTIK);

        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id, 'status_keputusan_direktur' => 'approved']);
        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

        $component = Livewire::test(DashboardSikartapPage::class)
            ->assertOk()
            ->assertSet('canStatistik', true)
            ->assertSet('canButuhTindakan', false)
            ->assertSet('canAntrianTahap', false);

        $statistik = $component->get('statistik');
        $this->assertSame(2, $statistik['total']);
        $this->assertSame(1, $statistik['approved']);
        $this->assertSame(1, $statistik['pending']);
    }

    public function test_antrian_tahap_widget_counts_by_stage(): void
    {
        $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_ANTRIAN_TAHAP);

        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id, 'status_berkas' => 'approved']);
        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus']);

        $antrian = Livewire::test(DashboardSikartapPage::class)->get('antrianTahap');

        $this->assertSame(1, $antrian['tes']);
        $this->assertSame(1, $antrian['wawancara']);
    }

    public function test_butuh_tindakan_widget_lists_pending_berkas_for_updater(): void
    {
        $user = $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_BUTUH_TINDAKAN);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']));

        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

        $items = Livewire::test(DashboardSikartapPage::class)->get('butuhTindakan');

        $this->assertArrayHasKey('berkas', $items);
        $this->assertSame(1, $items['berkas']['count']);
    }

    public function test_mcu_widget_only_counts_the_relevant_role(): void
    {
        $lab = $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_BUTUH_TINDAKAN);
        $lab->givePermissionTo(Permission::firstOrCreate(['name' => CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value, 'guard_name' => 'web']));

        $sikartap = Sikartap::create([
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
        ]);
        $sikartap->mcuNurseAssessment()->create(['tinggi_badan' => 170, 'berat_badan' => 65, 'tekanan_darah' => '120/80', 'nadi' => 80, 'suhu' => 36.5, 'pernafasan' => 20]);

        $items = Livewire::test(DashboardSikartapPage::class)->get('butuhTindakan');

        $this->assertArrayHasKey('mcu_lab', $items);
        $this->assertSame(1, $items['mcu_lab']['count']);
        $this->assertArrayNotHasKey('mcu_perawat', $items);
        $this->assertArrayNotHasKey('mcu_dokter', $items);
    }

    private function makeMcuEligibleSikartap(): Sikartap
    {
        return Sikartap::create([
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
        ]);
    }

    /** Instruksi user 2026-08-26: staff yang CUMA punya permission MCU (tanpa ViewAny:Sikartap)
     * cuma lihat 3 kolom tabel (Nama/NIP/Aksi), row-nya juga cuma yang beneran bisa dia aksi. */
    public function test_tabel_shows_only_three_columns_for_mcu_only_staff(): void
    {
        $lab = $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_STATISTIK);
        $lab->givePermissionTo(Permission::firstOrCreate(['name' => CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value, 'guard_name' => 'web']));

        $eligible = $this->makeMcuEligibleSikartap();
        $notEligible = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

        $component = Livewire::test(DashboardSikartapPage::class)
            ->assertSet('canViewSikartap', false)
            ->assertSet('canInputMcu', true)
            ->assertSet('canTabelPengajuan', true)
            ->assertDontSee('Berkas')
            ->assertDontSee('Wawancara')
            ->assertDontSee('Keputusan Direktur');

        $pengajuan = $component->get('pengajuan');
        $this->assertTrue($pengajuan->contains('id', $eligible->id));
        $this->assertFalse($pengajuan->contains('id', $notEligible->id));
    }

    /** Staff dengan ViewAny:Sikartap + permission MCU lihat tabel penuh, Aksi-nya nambah
     * ikon MCU di samping ikon mata (bukan gantiin). */
    public function test_tabel_shows_full_columns_plus_mcu_icon_for_combined_permission(): void
    {
        $user = $this->actingAsWithPermission(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_STATISTIK);
        $user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value, 'guard_name' => 'web']),
        ]);

        $eligible = $this->makeMcuEligibleSikartap();

        $component = Livewire::test(DashboardSikartapPage::class)
            ->assertSet('canViewSikartap', true)
            ->assertSet('canInputMcu', true)
            ->assertSee('Berkas')
            ->assertSee('Keputusan Direktur');

        $actions = $component->instance()->mcuActionsFor($eligible->fresh());
        $this->assertCount(1, $actions);
        $this->assertSame(route('sikartap.mcu.laboratorium', $eligible), $actions[0]['url']);
    }
}
