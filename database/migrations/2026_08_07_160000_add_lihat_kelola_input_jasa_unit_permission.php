<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Input Jasa Unit (`/keuangan/jasa-unit`) sebelumnya numpang 2 gate campuran: SISTEM_KEUANGAN
 * buat lihat, EDIT_MAPPING_KOMPONEN_GAJI buat simpan - sama pola dengan Omset sebelum ini
 * (2026_08_07_150000). Dipisah jadi LIHAT_INPUT_JASA_UNIT & KELOLA_INPUT_JASA_UNIT.
 *
 * - LIHAT_INPUT_JASA_UNIT -> role yang sudah punya sistem_keuangan
 * - KELOLA_INPUT_JASA_UNIT -> role yang sudah punya edit_mapping_komponen_gaji (gate lama)
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihat = Permission::firstOrCreate(['name' => 'lihat_input_jasa_unit', 'guard_name' => 'web']);
        $kelola = Permission::firstOrCreate(['name' => 'kelola_input_jasa_unit', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_keuangan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($lihat));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'edit_mapping_komponen_gaji'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($kelola));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Input Jasa Unit tanpa sengaja.
    }
};
