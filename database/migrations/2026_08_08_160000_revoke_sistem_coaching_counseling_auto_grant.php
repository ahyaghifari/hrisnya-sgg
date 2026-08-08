<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Balik keputusan migration sebelumnya (2026_08_08_150000) - SISTEM_COACHING_COUNSELING
 * SEMPAT digrant otomatis ke semua role existing sebagai safety-net biar tidak ada atasan yang
 * mendadak terkunci saat gerbangnya diaktifkan. Instruksi user 2026-08-08: tidak mau auto-grant
 * sama sekali, permission ini harus di-assign manual satu-satu lewat Hak Akses/Role - sama pola
 * dengan permission manual lain (EDIT_MAPPING_KOMPONEN_GAJI dkk), bukan dibootstrap ke semua role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_coaching_counseling'))
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('sistem_coaching_counseling'));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - lihat migration 2026_08_08_150000 untuk alasan yang sama
        // (tidak mau tiba-tiba grant/cabut massal lewat rollback).
    }
};
