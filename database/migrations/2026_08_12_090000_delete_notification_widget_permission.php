<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus permission Shield "View:NotificationWidget" - widget-nya sudah dilepas dari
 * ->widgets() AdminPanelProvider (2026-08-11), tabel notifikasi pengajuan izin/cuti/lembur
 * di dashboard admin dihilangkan. File widget-nya sendiri tetap dibiarkan ada (gampang
 * dipasang balik), tapi permission Shield-nya sudah yatim (tidak ada lagi widget terdaftar
 * yang dipetakan ke sini) - dihapus, pola sama migration cleanup permission yatim lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanIds = Permission::query()
            ->where('name', 'View:NotificationWidget')
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
        // Data cleanup, bukan schema — kalau perlu balikin, pasang lagi widget-nya ke
        // ->widgets() lalu jalankan shield:generate.
    }
};
