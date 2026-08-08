<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Fitur baru "Import Akun Karyawan" (/kepegawaian/karyawan/import) - permission manual
 * IMPORT_KARYAWAN (bukan Shield Verb:Subject, operasinya beda dari Create:User biasa - bulk
 * dengan preview Baru/Berubah/Tidak Ada di File). Safety net: role yang sudah punya
 * Create:User (bisa bikin 1 akun karyawan manual) otomatis dapat izin import juga.
 */
return new class extends Migration
{
    public function up(): void
    {
        $importKaryawan = Permission::firstOrCreate(['name' => 'import_karyawan', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Create:User'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($importKaryawan));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari fitur import tanpa sengaja.
    }
};
