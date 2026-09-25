<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * status enum -> string (perlu nilai 'cancelled' baru, ikuti pola project - enum yang butuh
     * nilai baru dikonversi ke string biasa daripada ALTER ENUM, lihat migration
     * add_supervisor_approver_type_to_m_approval_flow_steps_table). cancelled_by/cancelled_at
     * pola sama attendance_requests. execution_log = log operasi executeApprovedSwap() persis
     * saat dieksekusi, dipakai revertApprovedSwap() supaya pembatalan bisa mengembalikan jadwal
     * dengan akurat untuk semua skenario (tukar in-place, pindah kepemilikan, timpa Libur, double
     * shift, tukar libur berpasangan) tanpa harus menebak ulang dari data sekarang.
     */
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->foreignUlid('cancelled_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->json('execution_log')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['cancelled_by', 'cancelled_at', 'execution_log']);
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
