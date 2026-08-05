<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama tampilan per baris (dipakai jadi payroll_items.name) - Koperasi & Bon mode Simple tetap
 * null (fallback ke label komponen bawaan, lihat AbstractManualLineCalculator), cuma Bon mode
 * Detail yang mengisinya supaya tiap rincian potongan tampil dengan namanya sendiri di slip
 * (bukan "Bon" generik). Lihat planning/2026-08-05-payroll-bon-detail-import-nama-potongan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->string('label')->nullable()->after('component');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
