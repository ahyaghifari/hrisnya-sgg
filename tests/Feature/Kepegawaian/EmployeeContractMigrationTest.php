<?php

namespace Tests\Feature\Kepegawaian;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** user_contracts + user_orientations -> employee_contracts (id sama, type kontrak|orientasi) dan rename izin tanpa mencampur akses. */
class EmployeeContractMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $file): object
    {
        return require base_path("database/migrations/{$file}.php");
    }

    private function seedOld(User $user): array
    {
        $company = Companies::firstOrCreate(['code' => 'TST'], ['name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $type = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id]);
        $contractId = (string) Str::ulid();
        $orientationId = (string) Str::ulid();

        DB::table('user_orientations')->insert([
            'id' => $orientationId, 'user_id' => $user->id, 'company_id' => $company->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'duration_months' => 3, 'source' => 'recruitment',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_contracts')->insert([
            'id' => $contractId, 'user_id' => $user->id, 'company_id' => $company->id, 'employee_type_id' => $type->id,
            'contract_start_date' => '2026-04-01', 'contract_end_date' => '2027-03-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$contractId, $orientationId, $type->id];
    }

    public function test_both_ledgers_are_merged_with_same_ids_and_old_tables_stay(): void
    {
        $migration = $this->migration('2026_11_19_100000_create_employee_contracts_table');
        $migration->down();

        $user = User::factory()->create();
        [$contractId, $orientationId, $typeId] = $this->seedOld($user);

        $migration->up();

        $employeeId = Employee::where('user_id', $user->id)->value('id');
        $kontrak = EmployeeContract::findOrFail($contractId);
        $this->assertSame('kontrak', $kontrak->type);
        $this->assertSame($employeeId, $kontrak->employee_id);
        $this->assertSame($typeId, $kontrak->employee_type_id);
        $this->assertSame('2026-04-01', $kontrak->start_date->toDateString());
        $this->assertSame('2027-03-31', $kontrak->end_date->toDateString());

        $orientasi = EmployeeContract::findOrFail($orientationId);
        $this->assertSame('orientasi', $orientasi->type);
        $this->assertSame(3, $orientasi->duration_months);
        $this->assertSame('recruitment', $orientasi->source);

        $this->assertSame(1, DB::table('user_contracts')->count());
        $this->assertSame(1, DB::table('user_orientations')->count());

        // Relasi langsung dari Employee, tanpa lewat User.
        $employee = Employee::find($employeeId);
        $this->assertSame([$contractId], $employee->contracts->pluck('id')->all());
        $this->assertSame([$orientationId], $employee->orientations->pluck('id')->all());
    }

    public function test_copy_refuses_when_a_row_has_no_employee(): void
    {
        $migration = $this->migration('2026_11_19_100000_create_employee_contracts_table');
        $migration->down();

        $user = User::factory()->create();
        $this->seedOld($user);
        Employee::where('user_id', $user->id)->delete();

        $this->expectException(\RuntimeException::class);
        $migration->up();
    }

    public function test_permission_rename_keeps_contract_and_orientation_access_separate(): void
    {
        Permission::where('name', 'like', '%:EmployeeContract')->orWhere('name', 'like', '%:EmployeeOrientation')->delete();

        $contractPerm = Permission::create(['name' => 'ViewAny:UserContract', 'guard_name' => 'web']);
        $orientationPerm = Permission::create(['name' => 'ViewAny:UserOrientation', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Hanya Orientasi', 'guard_name' => 'web']);
        $role->givePermissionTo($orientationPerm);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->migration('2026_11_19_100100_rename_contract_orientation_permissions_to_employee_contract')->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($contractPerm->id, Permission::where('name', 'ViewAny:EmployeeContract')->value('id'));
        $this->assertSame($orientationPerm->id, Permission::where('name', 'ViewAny:EmployeeOrientation')->value('id'));
        $user = $user->fresh();
        $this->assertTrue($user->can('ViewAny:EmployeeOrientation'));
        $this->assertFalse($user->can('ViewAny:EmployeeContract'), 'role orientasi tidak boleh ikut mendapat akses kontrak');
    }
}