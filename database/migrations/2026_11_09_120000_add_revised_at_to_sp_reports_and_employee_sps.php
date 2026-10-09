<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda dokumen yang pernah direvisi setelah FIX (tanda tangan lama dibatalkan, TTD diulang).
 * Alur TTD BA yang direvisi: HC pembuat, atasan langsung, mengetahui, karyawan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sp_reports', 'employee_sps'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->timestamp('revised_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['sp_reports', 'employee_sps'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('revised_at'));
        }
    }
};