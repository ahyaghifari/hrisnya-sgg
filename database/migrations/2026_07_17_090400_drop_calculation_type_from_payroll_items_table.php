<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dihapus sesuai keputusan user — kolom ini sudah jadi metadata tampilan doang, tidak
     * ada logic yang membacanya (satu-satunya pembaca, SalaryComponentAssignments, ikut
     * dihapus di migration setelah ini). Lihat planning Fase 1 §Addendum simplifikasi.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('calculation_type');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('calculation_type')->nullable()->after('type');
        });
    }
};
