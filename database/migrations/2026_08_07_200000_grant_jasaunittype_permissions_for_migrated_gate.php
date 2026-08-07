<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Jasa Unit (types, `/keuangan/jasa-unit-types`) sekarang punya Filament Resource + Policy +
 * Shield permission sendiri (JasaUnitTypeResource, digenerate via
 * `shield:generate --resource=JasaUnitTypeResource`) - sebelumnya numpang 2 gate campuran:
 * SISTEM_KEUANGAN buat lihat, EDIT_MAPPING_KOMPONEN_GAJI buat simpan. Dipindah ke
 * ViewAny:JasaUnitType (lihat) & Update:JasaUnitType (simpan/kelola).
 *
 * - ViewAny:JasaUnitType -> role yang sudah punya sistem_keuangan
 * - Update:JasaUnitType -> role yang sudah punya edit_mapping_komponen_gaji (gate lama)
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:JasaUnitType')->where('guard_name', 'web')->first();
        $update = Permission::where('name', 'Update:JasaUnitType')->where('guard_name', 'web')->first();

        if (! $viewAny || ! $update) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($viewAny));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'edit_mapping_komponen_gaji'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($update));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Jasa Unit tanpa sengaja.
    }
};
