<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Koperasi & Tanggungan BPJS sudah punya UserKoperasiPolicy/BpjsDependentPolicy (Shield-style,
 * di-seed manual lewat CustomPermissionsSeeder::seedManualPolicyPermissions() karena modelnya
 * tidak punya Filament Resource) - Create/Update/Delete sudah dipakai buat tombol tambah/edit/
 * hapus, tapi mount() 2 halaman ini masih pakai SISTEM_KEUANGAN. Dipindah ke
 * ViewAny:UserKoperasi/ViewAny:BpjsDependent - migration ini cuma safety net-nya, sama pola
 * PayrollPeriod/Payroll/Incentive sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:UserKoperasi', 'ViewAny:BpjsDependent'];

        $permissions = Permission::whereIn('name', $names)->where('guard_name', 'web')->get();

        if ($permissions->isEmpty()) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Koperasi/Tanggungan BPJS tanpa sengaja.
    }
};
