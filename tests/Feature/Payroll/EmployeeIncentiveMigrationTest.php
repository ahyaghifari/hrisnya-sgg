<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** incentives -> employee_incentives: salinan data (id sama) dan rename permission tanpa membuat role kehilangan akses. */
class EmployeeIncentiveMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $file): object
    {
        return require base_path("database/migrations/{$file}.php");
    }

    private function oldRow(User $user, string $amount = 'cipher'): string
    {
        $id = (string) Str::ulid();
        DB::table('incentives')->insert([
            'id' => $id, 'user_id' => $user->id, 'name' => 'Insentif', 'incentive_date' => '2026-07-01', 'amount' => $amount, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_rows_are_copied_with_same_id_and_old_table_is_untouched(): void
    {
        $create = $this->migration('2026_11_18_110000_create_employee_incentives_table');
        $create->down();

        $user = User::factory()->create();
        $id = $this->oldRow($user, 'ciphertext-apa-adanya');

        $create->up();

        $copied = DB::table('employee_incentives')->where('id', $id)->first();
        $this->assertSame(Employee::where('user_id', $user->id)->value('id'), $copied->employee_id);
        $this->assertSame('ciphertext-apa-adanya', $copied->amount);
        $this->assertSame(1, DB::table('incentives')->count());
    }

    public function test_copy_refuses_when_a_member_has_no_employee_row(): void
    {
        $create = $this->migration('2026_11_18_110000_create_employee_incentives_table');
        $create->down();

        $user = User::factory()->create();
        $this->oldRow($user);
        Employee::where('user_id', $user->id)->delete();

        $this->expectException(\RuntimeException::class);
        $create->up();
    }

    public function test_permission_rename_keeps_role_and_direct_grants(): void
    {
        // Di produksi permission baru belum ada; DB test sudah punya (dibuat dari daftar resource saat ini).
        Permission::where('name', 'like', '%:EmployeeIncentive')->delete();

        $old = Permission::create(['name' => 'ViewAny:Incentive', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Keuangan Test', 'guard_name' => 'web']);
        $role->givePermissionTo($old);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue($user->fresh()->can('ViewAny:Incentive'));

        $this->migration('2026_11_18_110100_rename_incentive_permissions_to_employee_incentive')->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($old->id, Permission::where('name', 'ViewAny:EmployeeIncentive')->value('id'), 'diganti nama di tempat, bukan dibuat ulang');
        $this->assertNull(Permission::where('name', 'ViewAny:Incentive')->first());
        $this->assertTrue($user->fresh()->can('ViewAny:EmployeeIncentive'));
    }

    public function test_permission_rename_merges_into_an_existing_new_permission(): void
    {
        $old = Permission::create(['name' => 'Create:Incentive', 'guard_name' => 'web']);
        $new = Permission::firstOrCreate(['name' => 'Create:EmployeeIncentive', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Keuangan Test', 'guard_name' => 'web']);
        $role->givePermissionTo($old);

        $this->migration('2026_11_18_110100_rename_incentive_permissions_to_employee_incentive')->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNull(Permission::where('name', 'Create:Incentive')->first());
        $this->assertTrue($role->fresh()->hasPermissionTo($new));
    }
}
