<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siapa & kapan payroll INI (bukan periodenya) terakhir di-(re)generate — beda dari
     * payroll_periods.generated_by yang cuma nyatet aksi Generate/Regenerate SELURUH periode.
     * Regenerate per-karyawan (PayrollService::regenerateSinglePayroll()) cuma nyentuh 1 baris
     * payroll, jadi butuh jejak sendiri di sini biar tetap terlacak siapa yang terakhir ngubah
     * baris ini secara spesifik.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignUlid('generated_by')->nullable()->after('is_paid')->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable()->after('generated_by');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['generated_by']);
            $table->dropColumn(['generated_by', 'generated_at']);
        });
    }
};
