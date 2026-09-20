<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * `tukar_shift_saya` (TUKAR_SHIFT_SAYA) dibuang - halaman /tukar-shift sekarang cukup digerbangi
 * User::hasShiftSchedule() (guard di config/portal-pages.php), detailnya per-pengajuan lewat
 * ShiftSwapRequestDetail::canAccess(). Baris permission + assignment ke role/user ikut dibersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()
            ->where('name', 'tukar_shift_saya')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();

        Permission::query()->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse — pola sama drop_jadwal_shift_saya_permission.
    }
};
