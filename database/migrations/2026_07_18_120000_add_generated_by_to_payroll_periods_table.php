<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siapa yang menekan Generate/Regenerate terakhir untuk periode ini — ditampilkan di slip
     * gaji bagian "Dibuat oleh" (lihat resources/views/pdf/payroll/payroll.blade.php).
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignUlid('generated_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropForeign(['generated_by']);
            $table->dropColumn('generated_by');
        });
    }
};
