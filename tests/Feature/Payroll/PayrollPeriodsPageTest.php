<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollPeriodsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Versi portal dari PayrollPeriodResource (/admin/payroll-periods) — custom, List.js buat
 * sort+pagination (pola sama PayrollsPage), BUKAN lagi reuse Table/Action/Schema Filament.
 * Create/Edit sekarang modal Livewire polos, otorisasi Create:PayrollPeriod/Update:PayrollPeriod
 * dicek manual (`Auth::user()->can(...)`) supaya guard yang sebelumnya nempel di Filament
 * CreateAction/EditAction tidak ikut hilang pas direplace.
 */
class PayrollPeriodsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(bool $withCrudPermission = false): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:PayrollPeriod', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:PayrollPeriod', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:PayrollPeriod', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::GENERATE_PAYROLL->value, 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'Keuangan Periode Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, CustomPermissionEnum::GENERATE_PAYROLL->value]);

        if ($withCrudPermission) {
            $role->givePermissionTo(['Create:PayrollPeriod', 'Update:PayrollPeriod', 'Delete:PayrollPeriod']);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PayrollPeriodsPage::class)
            ->assertForbidden();
    }

    public function test_table_renders_existing_periods(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->assertOk()
            ->assertSee('Company TST');
    }

    public function test_create_button_hidden_without_permission_and_visible_with_it(): void
    {
        $this->makeCompany();

        Livewire::actingAs($this->actingAsFinance(withCrudPermission: false))
            ->test(PayrollPeriodsPage::class)
            ->assertDontSee('Tambah Periode Gaji');

        Livewire::actingAs($this->actingAsFinance(withCrudPermission: true))
            ->test(PayrollPeriodsPage::class)
            ->assertSee('Tambah Periode Gaji');
    }

    public function test_edit_button_hidden_without_permission_but_generate_still_visible(): void
    {
        $company = $this->makeCompany();
        PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($this->actingAsFinance(withCrudPermission: false))
            ->test(PayrollPeriodsPage::class)
            ->assertDontSee('Edit')
            ->assertSee('Generate Payroll');
    }

    public function test_user_with_crud_permission_can_create_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openCreateModal')
            ->set('formCompanyId', $company->id)
            ->set('formStartDate', '2026-09-01')
            ->set('formEndDate', '2026-09-30')
            ->call('saveForm');

        $this->assertDatabaseHas('payroll_periods', [
            'company_id' => $company->id,
            'start_date' => '2026-09-01 00:00:00',
            'end_date' => '2026-09-30 00:00:00',
        ]);
    }

    /**
     * signer_id sengaja terpisah dari generated_by (App\Jobs\GeneratePayrollJob mengisi
     * generated_by otomatis pakai Auth::id() tiap kali Generate/Regenerate diklik) - ini yang
     * dipilih manual buat "Dibuat oleh" di slip gaji, harus tetap tersimpan apa adanya.
     */
    public function test_user_with_crud_permission_can_set_signer(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $signer = User::factory()->create(['name' => 'Kepala Keuangan']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openCreateModal')
            ->set('formCompanyId', $company->id)
            ->set('formStartDate', '2026-09-01')
            ->set('formEndDate', '2026-09-30')
            ->set('formSignerId', $signer->id)
            ->call('saveForm');

        $this->assertDatabaseHas('payroll_periods', [
            'company_id' => $company->id,
            'signer_id' => $signer->id,
        ]);
    }

    public function test_user_without_crud_permission_cannot_open_create_modal(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: false);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openCreateModal')
            ->assertForbidden();
    }

    public function test_user_with_crud_permission_can_edit_unprocessed_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openEditModal', $period->id)
            ->set('formEndDate', '2026-08-05')
            ->call('saveForm');

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'end_date' => '2026-08-05']);
    }

    public function test_cannot_edit_already_processed_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openEditModal', $period->id)
            ->assertForbidden();
    }

    /**
     * Penandatangan & Tanggal Bayar murni informasional (tidak dipakai perhitungan payroll),
     * jadi sengaja TIDAK ikut abort_if(processed_at) seperti openEditModal - tetap bisa diubah
     * lewat modal terpisah (openSignerModal/saveSignerModal) walau periode sudah digenerate.
     */
    public function test_can_update_signer_and_payment_date_on_processed_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $signer = User::factory()->create(['name' => 'Kepala Keuangan']);
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('openSignerModal', $period->id)
            ->set('signerFormPaymentDate', '2026-08-05')
            ->set('signerFormSignerId', $signer->id)
            ->call('saveSignerModal');

        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'signer_id' => $signer->id,
            'payment_date' => '2026-08-05 00:00:00',
        ]);
    }

    public function test_delete_button_hidden_without_permission_but_visible_with_it(): void
    {
        $company = $this->makeCompany();
        PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($this->actingAsFinance(withCrudPermission: false))
            ->test(PayrollPeriodsPage::class)
            ->assertDontSee('Hapus');

        Livewire::actingAs($this->actingAsFinance(withCrudPermission: true))
            ->test(PayrollPeriodsPage::class)
            ->assertSee('Hapus');
    }

    public function test_user_with_permission_can_delete_unprocessed_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('deletePeriod', $period->id);

        $this->assertDatabaseMissing('payroll_periods', ['id' => $period->id]);
    }

    public function test_cannot_delete_already_processed_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('deletePeriod', $period->id)
            ->assertForbidden();

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id]);
    }

    public function test_user_without_permission_cannot_delete_period(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: false);
        $company = $this->makeCompany();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('deletePeriod', $period->id)
            ->assertForbidden();

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id]);
    }

    public function test_generate_payroll_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('generatePayroll', $period->id);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\GeneratePayrollJob::class);
    }

    public function test_regenerate_dispatches_job_even_with_imported_incentives(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);
        $payroll = \App\Models\Payroll\Payroll::create(['user_id' => User::factory()->create()->id, 'payroll_period_id' => $period->id]);
        \App\Models\Payroll\PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Insentif',
            'type' => 'earning',
            'amount' => 500000,
            'source_type' => 'insentif',
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->call('regeneratePayroll', $period->id);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\RegeneratePayrollJob::class);
    }
}
