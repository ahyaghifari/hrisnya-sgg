<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Batal SP (instruksi user 2026-09-25) - soft-cancel, bukan hard delete: SP tetap ada
 * riwayatnya, ditandai batal + alasan + siapa yang membatalkan. SP batal tidak lagi dihitung
 * aktif (EmployeeSp::scopeActive()). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('knows_by');
            $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            $table->foreignUlid('cancelled_by')->nullable()->after('cancelled_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancelled_reason']);
        });
    }
};
