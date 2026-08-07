<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Komponen Gaji, Penerima Komponen Gaji, Mapping Komponen Gaji sudah punya permission EDIT_*
 * masing-masing (edit_komponen_gaji/edit_penerima_komponen_gaji/edit_mapping_komponen_gaji) tapi
 * belum ada pasangan LIHAT_*-nya - mount() 3 halaman ini masih numpang SISTEM_KEUANGAN. Sama
 * pola dengan LIHAT_OMSET/LIHAT_INPUT_JASA_UNIT sebelumnya: buat 3 permission baru + assign ke
 * role yang sudah punya sistem_keuangan (safety net).
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['lihat_komponen_gaji', 'lihat_penerima_komponen_gaji', 'lihat_mapping_komponen_gaji'];

        $permissions = collect($names)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

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
        // dari 3 halaman ini tanpa sengaja.
    }
};
