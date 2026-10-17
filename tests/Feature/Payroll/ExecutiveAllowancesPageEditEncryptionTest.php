<?php

namespace Tests\Feature\Payroll;

use App\Livewire\Keuangan\ExecutiveAllowancesPage;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeExecutiveAllowance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Bug ditemukan 2026-08-21: editingId ? ::whereKey()->update() : ::create() - jalur EDIT pakai
 * query builder langsung, melewati cast PayrollEncrypted, nulis 'amount' plaintext mentah (sama
 * pola KoperasiPage::saveEdit()/IncentivesPage::saveForm() yang juga diperbaiki). */
class ExecutiveAllowancesPageEditEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_allowance_keeps_amount_encrypted(): void
    {
        Permission::firstOrCreate(['name' => 'ViewAny:EmployeeExecutiveAllowance', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:EmployeeExecutiveAllowance', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Exec Allowance Edit Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:EmployeeExecutiveAllowance', 'Update:EmployeeExecutiveAllowance']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $execId = User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS[0];
        $exec = new User();
        $exec->forceFill(['id' => $execId, 'name' => 'Presiden Direktur Uji', 'email' => 'presdir-editenc@test.local', 'password' => bcrypt('secret'), 'nip' => '00096', 'is_active' => true]);
        $exec->save();

        $allowance = EmployeeExecutiveAllowance::create([
            'employee_id' => Employee::where('user_id', $exec->id)->value('id'),
            'component' => 'gaji_pokok',
            'type' => 'earning',
            'label' => 'Gaji Pokok',
            'amount' => 10_000_000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ExecutiveAllowancesPage::class)
            ->call('openEditModal', $allowance->id)
            ->set('formAmount', '12000000')
            ->call('saveForm');

        $raw = DB::table('employee_executive_allowances')->where('id', $allowance->id)->value('amount');
        $this->assertFalse(is_numeric(trim((string) $raw)), 'amount tersimpan plaintext (angka mentah), harusnya ciphertext.');
        $this->assertSame(12_000_000.0, (float) $allowance->fresh()->amount);
    }
}
