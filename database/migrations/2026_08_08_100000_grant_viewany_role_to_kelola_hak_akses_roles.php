<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Dropdown nav "Manajemen Pengguna" (Role + Hak Akses) - halaman baru "Role"
 * (RolesPage, CRUD role polos tanpa checkbox permission) digerbangi ViewAny:Role (Shield,
 * RolePolicy sudah ada dari admin panel tapi belum pernah dipasang ke portal). Safety net:
 * role yang sudah punya kelola_hak_akses (gerbang dropdown-nya) otomatis dapat ViewAny:Role
 * juga, sama pola migration lain di sesi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAnyRole = Permission::where('name', 'ViewAny:Role')->where('guard_name', 'web')->first();

        if (! $viewAnyRole) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'kelola_hak_akses'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($viewAnyRole));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Role tanpa sengaja.
    }
};
