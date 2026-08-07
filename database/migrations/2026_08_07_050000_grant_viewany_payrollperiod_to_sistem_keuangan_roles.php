<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Gerbang lihat halaman Periode Gaji dipindah dari SISTEM_KEUANGAN (gate kasar "boleh buka
 * menu Penggajian") ke ViewAny:PayrollPeriod (Shield, per-resource) - instruksi user 2026-08-07,
 * langkah pertama migrasi bertahap sub-halaman keuangan lainnya ke pola sama.
 *
 * Semua role yang sudah punya sistem_keuangan di-assign ViewAny:PayrollPeriod juga di sini,
 * supaya tidak ada role yang tiba-tiba kehilangan akses begitu gate baru ini aktif - sama pola
 * migration tugas_saya (2026_08_07_030000).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::where('name', 'ViewAny:PayrollPeriod')->where('guard_name', 'web')->first();

        if (! $permission) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut ViewAny:PayrollPeriod lewat rollback bisa
        // mengunci role dari halaman Periode Gaji tanpa sengaja.
    }
};
