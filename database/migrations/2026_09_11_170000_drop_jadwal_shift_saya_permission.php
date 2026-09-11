<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * `jadwal_shift_saya` (JADWAL_SHIFT_SAYA) — tidak pernah di-assign ke role manapun dan tidak
 * digate di kode. Rute 'presensi.jadwal-saya' pakai `permission => null` + guard
 * `User::hasShiftSchedule()` (lihat config/portal-pages.php), bukan permission ini. Cuma
 * dipakai buat subject grouping ('ShiftSwapRequests') di RoleForm — grouping tanpa isi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()
            ->where('name', 'jadwal_shift_saya')
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
