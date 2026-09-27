<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission buka/tutup periode Pengajuan Karyawan Tetap (BUKA_TUTUP_PENGAJUAN_KARTAP). Dibuat di
 * sini + digrant ke role yang sudah bisa edit Pengaturan Kepegawaian (edit_pengaturan_kepegawaian
 * sudah boleh mengubah semua pengaturan, jadi wajar tidak kehilangan akses) dan ke Super Admin -
 * define_via_gate=false, Super Admin tidak bypass otomatis, harus punya permission literal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'buka_tutup_pengajuan_kartap', 'guard_name' => 'web']);

        Role::query()
            ->whereIn('name', ['super_admin', 'Super Admin'])
            ->orWhereHas('permissions', fn ($q) => $q->where('name', 'edit_pengaturan_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut lewat rollback bisa mengunci role dari buka/tutup
        // pengajuan tanpa sengaja (pola sama migration permission lain).
    }
};
