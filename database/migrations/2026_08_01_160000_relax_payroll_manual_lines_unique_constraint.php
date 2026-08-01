<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Longgarkan constraint dari migration 2026_07_19_065923_* — Bon sekarang punya mode "Detail"
 * (setting `bon_metode`, PayrollComponentEnum::requiredSettings()) yang butuh BANYAK baris
 * payroll_manual_lines per (periode, user, component=bon), rincian potongan satu-satu. Guard
 * "1 baris per periode" buat mode Simple (dan tetap buat Koperasi/Insentif import yang tidak
 * berubah) sekarang murni di kode (PayrollDetailPage::saveManualLine()), bukan lagi dijamin DB -
 * index biasa dipertahankan (bukan dihapus total) karena kolom ini tetap sering di-query bareng.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index baru HARUS dibuat dulu sebelum unique lama di-drop - MySQL pakai index unique itu
        // buat mendukung foreign key payroll_period_id, drop langsung tanpa index pengganti akan
        // gagal ("needed in a foreign key constraint").
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->index(['payroll_period_id', 'user_id', 'component'], 'payroll_manual_lines_period_user_component_idx');
        });

        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->dropUnique('payroll_manual_lines_period_user_component_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->unique(['payroll_period_id', 'user_id', 'component'], 'payroll_manual_lines_period_user_component_unique');
        });

        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->dropIndex('payroll_manual_lines_period_user_component_idx');
        });
    }
};
