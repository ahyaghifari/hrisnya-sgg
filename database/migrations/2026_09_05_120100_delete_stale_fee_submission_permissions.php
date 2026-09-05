<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * FeeSubmissionResource (halaman admin "Pengajuan Fee") dihapus total (2026-09-05, lihat
 * migration drop_fee_submissions_and_fee_items_tables) - permission Shield "Verb:FeeSubmission"
 * jadi ngambang (ungrouped) di halaman Role karena Resource-nya sudah tidak terdaftar. Pola
 * sama delete_stale_permissions_for_removed_resources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::query()
            ->where('name', 'like', '%:FeeSubmission')
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin, generate ulang lewat Shield.
    }
};
