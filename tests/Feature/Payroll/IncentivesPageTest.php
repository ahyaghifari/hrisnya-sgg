<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\IncentivesPage;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IncentivesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(bool $withCrudPermission = false): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Incentive', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Incentive', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:Incentive', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'Keuangan Insentif Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

        if ($withCrudPermission) {
            $role->givePermissionTo(['Create:Incentive', 'Update:Incentive', 'Delete:Incentive']);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(IncentivesPage::class)
            ->assertForbidden();
    }

    public function test_can_create_incentive(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $employee->id]);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->set('companyId', $company->id)
            ->call('openCreateModal')
            ->set('formUserId', $employee->id)
            ->set('formName', 'Insentif Juli')
            ->set('formDate', '2026-07-15')
            ->set('formAmount', '500000')
            ->call('saveForm');

        $this->assertDatabaseHas('incentives', ['user_id' => $employee->id, 'name' => 'Insentif Juli', 'amount' => 500000, 'is_active' => true]);
    }

    /** Bug ditemukan 2026-08-21: editingId ? ::whereKey()->update() : ::create() - jalur EDIT
     * pakai query builder langsung, melewati cast PayrollEncrypted, nulis 'amount' plaintext
     * mentah (sama pola KoperasiPage::saveEdit() yang sudah diperbaiki lebih dulu). Dicek lewat
     * DB::table() raw (bukan assertDatabaseHas polos) - ciphertext AES itu string panjang, kalau
     * masih raw sama dengan angka yang di-set berarti bug-nya balik lagi. */
    public function test_editing_incentive_keeps_amount_encrypted(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id]);
        $incentive = Incentive::create([
            'user_id' => $employee->id,
            'amount' => 500000,
            'is_active' => true,
        ]);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->set('companyId', $company->id)
            ->call('openEditModal', $incentive->id)
            ->set('formAmount', '750000')
            ->call('saveForm');

        $raw = \Illuminate\Support\Facades\DB::table('incentives')->where('id', $incentive->id)->value('amount');
        $this->assertFalse(is_numeric(trim((string) $raw)), 'amount tersimpan plaintext (angka mentah), harusnya ciphertext.');
        $this->assertSame(750_000.0, (float) $incentive->fresh()->amount);
    }

    public function test_can_toggle_incentive_active(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id]);
        $incentive = Incentive::create([
            'user_id' => $employee->id,
            'amount' => 500000,
            'is_active' => true,
        ]);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->set('companyId', $company->id)
            ->call('toggleActive', $incentive->id);

        $this->assertDatabaseHas('incentives', ['id' => $incentive->id, 'is_active' => false]);
    }

    public function test_can_create_incentive_with_only_required_fields(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $employee->id]);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->set('companyId', $company->id)
            ->call('openCreateModal')
            ->set('formUserId', $employee->id)
            ->set('formAmount', '250000')
            ->call('saveForm');

        // Nama Program & Tanggal opsional — tanggal default ke hari ini kalau dikosongkan.
        $incentive = \App\Models\Incentive\Incentive::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(250000.0, (float) $incentive->amount);
        $this->assertNull($incentive->name);
        $this->assertSame(now()->toDateString(), $incentive->incentive_date->toDateString());
    }

    public function test_without_crud_permission_cannot_open_create_modal(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: false);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->call('openCreateModal')
            ->assertForbidden();
    }

    public function test_can_delete_incentive(): void
    {
        $finance = $this->actingAsFinance(withCrudPermission: true);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id]);
        $incentive = Incentive::create([
            'user_id' => $employee->id,
            'name' => 'Insentif Juli',
            'incentive_date' => '2026-07-15',
            'amount' => 500000,
        ]);

        Livewire::actingAs($finance)
            ->test(IncentivesPage::class)
            ->set('companyId', $company->id)
            ->call('removeIncentive', $incentive->id);

        $this->assertDatabaseMissing('incentives', ['id' => $incentive->id]);
    }
}
