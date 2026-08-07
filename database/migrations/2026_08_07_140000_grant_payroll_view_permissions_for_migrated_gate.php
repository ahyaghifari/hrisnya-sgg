<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Gerbang lihat halaman Payroll (list & detail) dipindah dari SISTEM_KEUANGAN ke
 * ViewAny:Payroll/View:Payroll (Shield, per-resource) - lanjutan pola yang sama dengan Periode
 * Gaji (2026_08_07_050000). Dua langkah supaya tidak ada yang kehilangan/pincang akses:
 *
 * 1. Role yang sudah punya sistem_keuangan (dulu satu-satunya gerbang) diberi ViewAny:Payroll +
 *    View:Payroll - safety net sama seperti migration Periode Gaji.
 * 2. Role yang SUDAH punya ViewAny:Payroll tapi belum View:Payroll (ada beberapa dari batch
 *    grant Shield sebelumnya) diberi View:Payroll juga - kalau tidak, begitu gate list pindah
 *    ke ViewAny:Payroll, role itu bisa lihat daftar Payroll tapi 403 begitu klik baris manapun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:Payroll')->where('guard_name', 'web')->first();
        $view = Permission::where('name', 'View:Payroll')->where('guard_name', 'web')->first();

        if (! $viewAny || ! $view) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(function (Role $role) use ($viewAny, $view) {
                $role->givePermissionTo($viewAny);
                $role->givePermissionTo($view);
            });

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'ViewAny:Payroll'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($view));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Payroll tanpa sengaja.
    }
};
