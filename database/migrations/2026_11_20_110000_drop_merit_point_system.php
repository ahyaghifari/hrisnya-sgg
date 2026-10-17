<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem Merit Point dihapus total (instruksi user 2026-10-17): tabel merit_point_transactions &
 * merit_point_rules, kolom user_hr_balances.merit_point, setting kepegawaian_merit_point_enabled,
 * dan permission MeritPointRule/MeritPointTransaction (verb Shield) + beri_merit_point. Tabel user_hr_balances sendiri
 * tetap (masih dipakai cache SP aktif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('merit_point_transactions');
        Schema::dropIfExists('merit_point_rules');

        if (Schema::hasColumn('user_hr_balances', 'merit_point')) {
            Schema::table('user_hr_balances', fn (Blueprint $table) => $table->dropColumn('merit_point'));
        }

        DB::table('settings')->where('key', 'kepegawaian_merit_point_enabled')->delete();

        $permissionIds = DB::table('permissions')
            ->where('name', 'like', '%:MeritPoint%')
            ->orWhere('name', 'beri_merit_point')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - data rule/transaksi yang sudah dibuang tidak bisa dipulihkan
        // dari sini; kalau Merit Point dibangun lagi, mulai dari migration create baru.
    }
};
