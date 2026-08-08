<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * SISTEM_TUGAS dihapus dari CustomPermissionEnum (2026-08-08) - satu-satunya pemakaiannya
 * (gerbang mount() TaskSettingsPage) diganti EDIT_PENGATURAN_TUGAS langsung, yang sudah punya
 * role holder sama persis (Super Admin), jadi tidak perlu safety net grant. Halaman
 * /tugas/pengaturan sendiri tidak pernah dilink di nav manapun (orphan sejak awal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'sistem_tugas')->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => 'sistem_tugas', 'guard_name' => 'web']);
    }
};
