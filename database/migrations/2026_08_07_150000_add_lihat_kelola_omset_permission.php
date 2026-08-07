<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Omset (`/keuangan/omset`) sebelumnya numpang 2 gate campuran: SISTEM_KEUANGAN buat lihat,
 * EDIT_MAPPING_KOMPONEN_GAJI (permission "Mapping Komponen Gaji", bukan miliknya sendiri) buat
 * simpan. Dipisah jadi 2 permission sendiri: LIHAT_OMSET & KELOLA_OMSET.
 *
 * Permission dibuat di sini + di-assign ke role yang sudah punya akses setara sebelumnya, supaya
 * tidak ada yang tiba-tiba kehilangan akses begitu gate baru ini aktif:
 * - LIHAT_OMSET -> role yang sudah punya sistem_keuangan
 * - KELOLA_OMSET -> role yang sudah punya edit_mapping_komponen_gaji (gate lama yang dipinjam)
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihatOmset = Permission::firstOrCreate(['name' => 'lihat_omset', 'guard_name' => 'web']);
        $kelolaOmset = Permission::firstOrCreate(['name' => 'kelola_omset', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($lihatOmset));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'edit_mapping_komponen_gaji'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($kelolaOmset));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Omset tanpa sengaja.
    }
};
