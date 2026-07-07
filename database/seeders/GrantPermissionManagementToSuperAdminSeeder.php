<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permission buat kelola halaman /admin/permissions (view/create/edit/delete_permission)
 * ditambahkan belakangan setelah PermissionResource dibuat — role "Super Admin" yang sudah
 * ada duluan (di company mana pun, bukan cuma holding) belum otomatis kebagian permission baru
 * ini kecuali PermissionsSeeder dijalankan ulang dari awal.
 *
 * Seeder ini cuma nambah 4 permission spesifik itu ke SEMUA role "Super Admin" (lintas company),
 * pakai givePermissionTo (bukan syncPermissions) supaya permission lain yang sudah di-assign
 * ke role tsb tidak ikut ke-reset/hilang.
 *
 * Aman dijalankan berulang kali (givePermissionTo Spatie idempotent, tidak dobel).
 */
class GrantPermissionManagementToSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $permissionNames = [
            'view_permission',
            'create_permission',
            'edit_permission',
            'delete_permission',
        ];

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->get();

        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->chunk(50, function ($roles) use ($permissions) {
                foreach ($roles as $role) {
                    $role->givePermissionTo($permissions);
                }
            });
    }
}
