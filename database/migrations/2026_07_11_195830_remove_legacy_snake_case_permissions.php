<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus permission format lama (`view_karyawan`, `approve_pengajuan_absensi`, dst —
 * snake_case, tanpa titik dua) yang jadi orphan sejak migrasi ke Filament Shield
 * (lihat 2026_07_08_230229_sync_filament_shield_permission_catalog.php). Permission
 * Shield selalu berformat `Verb:Subject`, jadi cukup filter nama yang TIDAK
 * mengandung ':'.
 *
 * Beberapa Gate::allows() lama (mis. app/Filament/Clusters/ShiftPattern) masih
 * memanggil nama permission ini — migrasi ini SENGAJA tidak mengubah kode
 * pemanggilnya, jadi Gate check tersebut akan selalu deny setelah migration ini
 * jalan sampai diganti ke permission Shield yang setara.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacyIds = Permission::query()
            ->where('name', 'not like', '%:%')
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        // model_has_permissions tidak lagi punya FK cascade ke permissions
        // (dilepas di 2025_09_11_114601_update_spatie_permission_for_ulid.php),
        // jadi hapus manual; role_has_permissions masih FK cascade.
        DB::table('model_has_permissions')->whereIn('permission_id', $legacyIds)->delete();

        Permission::query()->whereIn('id', $legacyIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse, sama seperti migration Shield sebelumnya —
        // permission lama yang sudah dihapus tidak boleh muncul lagi lewat rollback.
    }
};
