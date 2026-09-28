<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `knows_by` (SP) sekarang WAJIB diisi saat SP dibuat (instruksi user 2026-09-28, revisi dari
 * "boleh menyusul/offline verbal" - lihat komentar lama di EmployeeSp::knowsBy()) - baik
 * CreateSpPage maupun SpService::approveRecommendation() sudah SELALU mengisinya, jadi ini
 * menegaskan jaminan itu di level DB juga. Backfill dulu (fallback ke created_by, konsisten
 * dengan pola approveRecommendation yang memang menyamakan keduanya) sebelum NOT NULL, jaga-jaga
 * ada baris lama di production yang lolos sebelum form mewajibkannya.
 *
 * FK-nya dibuat ulang (drop lalu constrained lagi) - MySQL menolak ->change() jadi NOT NULL
 * selama FK masih `nullOnDelete()` (SET NULL butuh kolom nullable). Diganti `restrictOnDelete()`,
 * sama seperti `created_by` di tabel ini - user yang masih jadi knows_by SP tidak boleh dihapus
 * begitu saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_sps')
            ->whereNull('knows_by')
            ->update(['knows_by' => DB::raw('created_by')]);

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropForeign(['knows_by']);
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->ulid('knows_by')->nullable(false)->change();
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->foreign('knows_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropForeign(['knows_by']);
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->ulid('knows_by')->nullable()->change();
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->foreign('knows_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
