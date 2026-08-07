<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Insentif (`/keuangan/insentif`) sudah punya IncentivePolicy (Shield-style, sama pola
 * PayrollPeriod/Payroll) - Create/Update/Delete:Incentive sudah dipakai buat tombol tambah/
 * edit/hapus, tapi mount() halamannya masih pakai SISTEM_KEUANGAN. Dipindah ke
 * ViewAny:Incentive - migration ini cuma safety net-nya, sama pola 2026_08_07_050000
 * (PayrollPeriod) & 2026_08_07_140000 (Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:Incentive')->where('guard_name', 'web')->first();

        if (! $viewAny) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($viewAny));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Insentif tanpa sengaja.
    }
};
