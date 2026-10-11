<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission buka_blokir_tanda_tangan: membuka blokir tanda tangan akun karyawan (lihat
 * SigningLockService). Auto-grant ke role yang SUDAH punya edit_pengaturan_kepegawaian (HC/SDM)
 * dan ke role Super Admin (Shield tidak memberi bypass otomatis, permission harus literal).
 * Role lain diatur manual lewat Hak Akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'buka_blokir_tanda_tangan', 'guard_name' => 'web']);

        Role::query()
            ->where('name', 'Super Admin')
            ->orWhereHas('permissions', fn ($q) => $q->where('name', 'edit_pengaturan_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration permission lain.
    }
};
