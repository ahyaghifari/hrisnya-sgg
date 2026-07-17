<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\MappingKomponenGajiPage;
use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\TenureAllowance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman satu pintu untuk 5 tabel config payroll yang sebelumnya tidak ada UI sama sekali
 * (pangkat_salaries, position_allowances, transport_allowances, tenure_allowances,
 * pangkat_bpjs_bases), lihat planning insentif/mapping sesi ini. Test ini fokus ke bagian
 * yang murni logic PHP (load/save) — bagian Kelebihan Jam Kerja/Lembur reuse Form/Table
 * Filament yang sudah ada, cuma dites render tidak error.
 */
class MappingKomponenGajiPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Mapping Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompanyWithActiveComponents(array $components): Companies
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        foreach ($components as $component) {
            CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => $component, 'is_active' => true]);
        }

        return $company;
    }

    public function test_only_active_components_appear_in_list(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['gaji_pokok', 'lembur']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->assertSee('Gaji Pokok')
            ->assertSee('Lembur')
            ->assertDontSee('Tunjangan Transportasi');
    }

    public function test_bpjs_entry_shows_if_either_bpjs_component_active(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['bpjs_kesehatan']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->assertSee('BPJS (Kesehatan & Ketenagakerjaan)');
    }

    public function test_save_gaji_pokok_persists_base_salary(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['gaji_pokok']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'gaji_pokok')
            ->set("gajiPokokValues.{$pangkat->id}", 4500000)
            ->call('saveGajiPokok');

        $this->assertDatabaseHas('pangkat_salaries', ['pangkat_id' => $pangkat->id, 'base_salary' => 4500000]);
    }

    public function test_save_bpjs_creates_mapping_and_empty_value_removes_it(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['bpjs_kesehatan']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);

        $component = Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'bpjs')
            ->set("bpjsValues.{$pangkat->id}", 'declared_value')
            ->call('saveBpjs');

        $this->assertDatabaseHas('pangkat_bpjs_bases', ['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'declared_value']);

        $component
            ->set("bpjsValues.{$pangkat->id}", '')
            ->call('saveBpjs');

        $this->assertDatabaseMissing('pangkat_bpjs_bases', ['pangkat_id' => $pangkat->id]);
    }

    public function test_save_transport_allowance_persists_per_kelas(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_transportasi']);
        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_transportasi')
            ->set("transportValues.{$kelas->id}", 250000)
            ->call('saveTransport');

        $this->assertDatabaseHas('transport_allowances', ['kelas_id' => $kelas->id, 'amount' => 250000]);
    }

    public function test_save_jabatan_matrix_persists_pangkat_kelas_combination(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_jabatan']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_jabatan')
            ->set("jabatanMatrix.{$pangkat->id}|{$kelas->id}", 300000)
            ->call('saveJabatan');

        $this->assertDatabaseHas('position_allowances', [
            'pangkat_id' => $pangkat->id,
            'kelas_id' => $kelas->id,
            'amount' => 300000,
        ]);
    }

    public function test_tenure_rows_can_be_added_saved_and_removed(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_masa_kerja']);

        $component = Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_masa_kerja')
            ->call('addTenureRow')
            ->set('tenureRows.0.min_years', 0)
            ->set('tenureRows.0.max_years', 5)
            ->set('tenureRows.0.amount', 100000)
            ->call('saveTenure');

        $this->assertDatabaseHas('tenure_allowances', [
            'company_id' => $company->id,
            'min_years' => 0,
            'max_years' => 5,
            'amount' => 100000,
        ]);

        $tenure = TenureAllowance::where('company_id', $company->id)->first();
        $component->set('tenureRows.0.id', $tenure->id) // saveTenure already back-fills id in real usage
            ->call('removeTenureRow', 0);

        $this->assertDatabaseMissing('tenure_allowances', ['id' => $tenure->id]);
    }

    public function test_kelebihan_jam_kerja_table_renders_without_error(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['kelebihan_jam_kerja']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'kelebihan_jam_kerja')
            ->assertOk();
    }

    public function test_lembur_table_renders_without_error(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['lembur']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'lembur')
            ->assertOk();
    }

    public function test_lembur_per_pangkat_metode_shows_rate_form_and_saves(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['lembur']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        Setting::create(['company_id' => $company->id, 'key' => 'lembur_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'per_pangkat', 'label' => 'Metode Lembur']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'lembur')
            ->assertSee('Rate lembur per jam')
            ->set("lemburRateValues.{$pangkat->id}", 25000)
            ->call('saveLemburRates');

        $this->assertDatabaseHas('pangkat_lembur_rates', ['pangkat_id' => $pangkat->id, 'amount_per_hour' => 25000]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MappingKomponenGajiPage::class)
            ->assertForbidden();
    }

    public function test_negative_gaji_pokok_is_rejected(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['gaji_pokok']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'gaji_pokok')
            ->set("gajiPokokValues.{$pangkat->id}", -100)
            ->call('saveGajiPokok')
            ->assertHasErrors(["gajiPokokValues.{$pangkat->id}"]);

        $this->assertDatabaseMissing('pangkat_salaries', ['pangkat_id' => $pangkat->id]);
    }

    public function test_negative_jabatan_matrix_value_is_rejected(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_jabatan']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_jabatan')
            ->set("jabatanMatrix.{$pangkat->id}|{$kelas->id}", -50000)
            ->call('saveJabatan')
            ->assertHasErrors(["jabatanMatrix.{$pangkat->id}|{$kelas->id}"]);

        $this->assertDatabaseMissing('position_allowances', ['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id]);
    }

    public function test_tenure_max_years_below_min_years_is_rejected(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_masa_kerja']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_masa_kerja')
            ->call('addTenureRow')
            ->set('tenureRows.0.min_years', 10)
            ->set('tenureRows.0.max_years', 5)
            ->set('tenureRows.0.amount', 100000)
            ->call('saveTenure')
            ->assertHasErrors(['tenureRows.0.max_years']);

        $this->assertDatabaseMissing('tenure_allowances', ['company_id' => $company->id]);
    }

    public function test_overlapping_tenure_brackets_are_rejected(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_masa_kerja']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_masa_kerja')
            ->call('addTenureRow')
            ->call('addTenureRow')
            ->set('tenureRows.0.min_years', 0)
            ->set('tenureRows.0.max_years', 5)
            ->set('tenureRows.0.amount', 100000)
            ->set('tenureRows.1.min_years', 3)
            ->set('tenureRows.1.max_years', 10)
            ->set('tenureRows.1.amount', 200000)
            ->call('saveTenure')
            ->assertHasErrors(['tenureRows.1.min_years']);

        $this->assertDatabaseMissing('tenure_allowances', ['company_id' => $company->id]);
    }

    public function test_non_overlapping_tenure_brackets_are_saved(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompanyWithActiveComponents(['tunjangan_masa_kerja']);

        Livewire::actingAs($finance)
            ->test(MappingKomponenGajiPage::class)
            ->call('selectCompany', $company->id)
            ->call('selectComponent', 'tunjangan_masa_kerja')
            ->call('addTenureRow')
            ->call('addTenureRow')
            ->set('tenureRows.0.min_years', 0)
            ->set('tenureRows.0.max_years', 5)
            ->set('tenureRows.0.amount', 100000)
            ->set('tenureRows.1.min_years', 6)
            ->set('tenureRows.1.max_years', '')
            ->set('tenureRows.1.amount', 200000)
            ->call('saveTenure')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenure_allowances', ['company_id' => $company->id, 'min_years' => 0, 'max_years' => 5]);
        $this->assertDatabaseHas('tenure_allowances', ['company_id' => $company->id, 'min_years' => 6, 'max_years' => null]);
    }
}
