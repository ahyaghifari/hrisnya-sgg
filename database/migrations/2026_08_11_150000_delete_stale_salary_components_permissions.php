<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus 10 permission Shield "Verb:SalaryComponents" - sisa Resource lama (tabel
 * salary_components sudah dihapus total di Payroll Fase 1, digantikan PayrollComponentEnum).
 * Sebelumnya di-list manual di RoleForm::portalGroups() biar tidak nangkring ungrouped,
 * sekarang dihapus total dari DB & listing-nya (permintaan user 2026-08-11), pola sama
 * 2026_08_07_000000_cleanup_orphaned_shield_permissions.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanIds = Permission::query()
            ->where('name', 'like', '%:SalaryComponents')
            ->pluck('id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();

        Permission::query()->whereIn('id', $orphanIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin, generate ulang lewat Shield.
    }
};
