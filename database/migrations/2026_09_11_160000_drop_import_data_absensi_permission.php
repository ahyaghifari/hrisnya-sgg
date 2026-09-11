<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * `import_data_absensi` (IMPORT_DATA_ABSENSI) — orphan sejak awal: tidak pernah dicek di kode
 * (nol `->can()`/gate), dan tidak pernah didaftarkan di RoleForm::portalGroups() maupun
 * baseEmployeePermissions() untuk bisa di-assign ke role manapun lewat UI. Import absensi
 * yang beneran jalan pakai SYNC_DATA_ABSENSI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()
            ->where('name', 'import_data_absensi')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();

        Permission::query()->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse — pola sama cleanup_orphaned_shield_permissions.
    }
};
