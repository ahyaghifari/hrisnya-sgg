<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Halaman Pengaturan Absensi sebelum ini cukup digerbangi SISTEM_ABSENSI (permission dropdown
 * modul, dicek di AttendanceSettingsPage::mount()) - siapapun yang bisa buka menu Absensi
 * otomatis bisa buka halaman cutoff/kuota perusahaan ini juga. Sekarang dipisah jadi permission
 * sendiri (LIHAT_PENGATURAN_ABSENSI), pola sama migration tugas_saya
 * (2026_08_07_030000_add_tugas_saya_permission_and_grant_existing_roles.php) - permission baru
 * langsung di-assign ke semua role yang SUDAH bisa lihat halaman ini (punya sistem_absensi ATAU
 * edit_pengaturan_absensi), supaya tidak ada role yang tiba-tiba 403 begitu gate ini aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'lihat_pengaturan_absensi',
            'guard_name' => 'web',
        ]);

        $roleIds = Permission::whereIn('name', ['sistem_absensi', 'edit_pengaturan_absensi'])
            ->where('guard_name', 'web')
            ->get()
            ->flatMap(fn (Permission $p) => $p->roles)
            ->pluck('id')
            ->unique();

        Role::whereIn('id', $roleIds)->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration cleanup/grant permission sebelumnya.
    }
};
