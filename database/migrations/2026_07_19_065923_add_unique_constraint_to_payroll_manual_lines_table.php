<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menutup race condition: cek "sudah punya baris atau belum" di kode (PayrollDetailPage,
     * import Bon/Insentif) tidak atomik — dua request bersamaan bisa dua-duanya lolos cek
     * sebelum salah satu commit. Constraint DB ini yang jadi jaminan sesungguhnya, bukan cuma
     * cek `isNotEmpty()` di aplikasi.
     */
    public function up(): void
    {
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->unique(['payroll_period_id', 'user_id', 'component'], 'payroll_manual_lines_period_user_component_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->dropUnique('payroll_manual_lines_period_user_component_unique');
        });
    }
};
