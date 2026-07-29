<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Golongan digabung ke Pangkat (lihat migration 2026_07_29_110000/110100) - Filament Resource
 * & Policy-nya dihapus dari kode, permission Shield "Verb:Golongans" jadi yatim (ngambang di
 * halaman Role, tidak nunjuk Resource manapun lagi). Pola sama dengan
 * 2026_07_12_160000_delete_stale_permissions_for_removed_resources.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::query()
            ->where('name', 'like', '%:Golongans')
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin, generate ulang lewat shield:generate
        // (Resource-nya sendiri sudah dihapus dari kode, jadi tidak akan ke-generate ulang).
    }
};
