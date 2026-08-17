<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename modul "Coaching & Counseling" jadi "Pembinaan Karyawan" (instruksi user 2026-08-17,
 * isi/fungsi modul TIDAK berubah, cuma nama). UPDATE nama baris permission langsung (bukan
 * delete+recreate) - role_has_permissions referensinya lewat permission_id, jadi assignment
 * role yang sudah ada TIDAK ikut hilang (pola sama 2026_08_08_150000_activate_sistem_coaching_
 * counseling_gate.php, cuma UPDATE bukan create/grant).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'sistem_coaching_counseling')
            ->update(['name' => 'sistem_pembinaan_karyawan']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'sistem_pembinaan_karyawan')
            ->update(['name' => 'sistem_coaching_counseling']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
