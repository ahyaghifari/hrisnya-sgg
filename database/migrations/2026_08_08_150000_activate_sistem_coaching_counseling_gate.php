<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * SISTEM_COACHING_COUNSELING sebelumnya cuma label pengelompokan di Hak Akses, tidak pernah
 * benar-benar dicek di kode manapun (centang/uncheck-nya nol pengaruh) - baru sekarang dipakai
 * sebagai gerbang beneran di CoachingPage/CreateCoachingPage/CoachingDetailPage (mount()) &
 * nav dropdown "Coaching & Counseling" (2026-08-08, laporan user). Safety net: karena
 * sebelumnya SIAPA SAJA yang punya bawahan otomatis bisa akses tanpa syarat permission apapun,
 * semua role yang sudah ada digrant permission ini supaya tidak ada atasan yang mendadak
 * terkunci - role baru ke depannya harus di-assign manual seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'sistem_coaching_counseling', 'guard_name' => 'web']);

        Role::where('guard_name', 'web')->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci semua
        // atasan dari fitur Coaching & Counseling tanpa sengaja.
    }
};
