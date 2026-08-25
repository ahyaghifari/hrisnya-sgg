<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Sikartap\DashboardSikartapPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Services\Sikartap\SikartapPeriodeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Tab Periode Berjalan/Lama + sub-tab Batch — rewrite periode_pengajuan.php legacy
 * (SikartapPeriodeHelper), dipakai widget tabel pengajuan di DashboardSikartapPage. */
class PengajuanPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHrd(): User
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hrd = User::factory()->create(['company_id' => $company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    public function test_periode_helper_classifies_by_age(): void
    {
        $this->assertFalse(SikartapPeriodeHelper::isPeriodeLama(now()->subMonth()));
        $this->assertTrue(SikartapPeriodeHelper::isPeriodeLama(now()->subMonths(4)));
    }

    public function test_periode_helper_assigns_batch_numbers(): void
    {
        $this->assertSame(0, SikartapPeriodeHelper::nomorBatch(now()->subMonth()));
        $this->assertSame(1, SikartapPeriodeHelper::nomorBatch(now()->subMonths(5)));
        $this->assertSame(2, SikartapPeriodeHelper::nomorBatch(now()->subMonths(11)));
    }

    public function test_list_page_default_tab_only_shows_current_period(): void
    {
        $hrd = $this->actingAsHrd();

        $current = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $hrd->company_id])->id]);
        $old = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $hrd->company_id])->id]);
        $old->forceFill(['created_at' => now()->subMonths(5)])->save();

        $pengajuan = Livewire::test(DashboardSikartapPage::class)
            ->assertSet('periode', 'berjalan')
            ->get('pengajuan');

        $this->assertTrue($pengajuan->contains('id', $current->id));
        $this->assertFalse($pengajuan->contains('id', $old->id));
    }

    public function test_switching_to_lama_tab_shows_old_records_and_batches(): void
    {
        $hrd = $this->actingAsHrd();

        $old = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $hrd->company_id])->id]);
        $old->forceFill(['created_at' => now()->subMonths(5)])->save();

        $component = Livewire::test(DashboardSikartapPage::class)->call('selectPeriode', 'lama');

        $pengajuan = $component->get('pengajuan');
        $this->assertTrue($pengajuan->contains('id', $old->id));

        $batches = $component->get('batches');
        $this->assertNotEmpty($batches);
        $this->assertSame(1, $batches[0]['n']);
        $this->assertSame(1, $batches[0]['count']);
    }

    public function test_export_returns_downloadable_response(): void
    {
        $hrd = $this->actingAsHrd();
        Sikartap::create(['user_id' => User::factory()->create(['company_id' => $hrd->company_id])->id]);

        Livewire::test(DashboardSikartapPage::class)
            ->call('export')
            ->assertFileDownloaded();
    }
}
