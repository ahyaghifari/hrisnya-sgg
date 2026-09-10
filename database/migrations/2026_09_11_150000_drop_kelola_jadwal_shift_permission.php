<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus custom permission `kelola_jadwal_shift`. Disederhanakan (2026-09-11): tab "Template
 * Shift" & "Detail Penjadwalan" di ScheduleDetailPage sekarang pakai permission Shield default
 * dari grup "Jadwal Shift" — `Update:ShiftTemplates` (rotasi) & `Update:ShiftScheduleAssignments`
 * (roster) — lihat ScheduleShiftRotationTab / ScheduleShiftRosterTab. Assignment role/user
 * yang masih menempel ikut dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()->where('name', 'kelola_jadwal_shift')->pluck('id');

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
