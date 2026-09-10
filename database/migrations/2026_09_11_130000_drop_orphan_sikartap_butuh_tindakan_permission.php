<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus permission yatim `lihat_dashboard_sikartap_butuh_tindakan` — widget "Butuh Tindakan"
 * di Dashboard Sikartap sudah tidak ada, case enum-nya sudah dibuang dari
 * App\Enum\CustomPermissionEnum, tidak ada satu pun kode yang mengeceknya. Tanpa migration ini
 * baris permission lama nyangkut di DB dan muncul di section "Lainnya" pada tree /hak-akses
 * (Shield generate tidak pernah membersihkan permission lama). Widget dashboard Sikartap yang
 * masih hidup (LIHAT_DASHBOARD_SIKARTAP_STATISTIK / _ANTRIAN_TAHAP) tidak disentuh.
 *
 * Assignment role/user yang masih menempel ikut dihapus — permission-nya sudah no-op jadi
 * tidak ada perubahan perilaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()
            ->where('name', 'lihat_dashboard_sikartap_butuh_tindakan')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // model_has_permissions tidak lagi FK-cascade ke permissions (lihat
        // 2025_09_11_114601_update_spatie_permission_for_ulid.php) — hapus manual;
        // role_has_permissions masih cascade.
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();

        Permission::query()->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse — permission yatim yang sudah dihapus tidak boleh muncul
        // lagi lewat rollback (pola sama cleanup_orphaned_shield_permissions).
    }
};
