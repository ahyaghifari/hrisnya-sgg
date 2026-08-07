<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsistensi approval engine: ApprovalFlowService::advanceStep() sudah lama coba menulis
 * rejected_by/rejected_at ke attendance_requests, tapi kolomnya tidak pernah ada - Laravel
 * membuang field itu diam-diam (bukan $fillable). Cancel sudah punya cancelled_by/cancelled_at,
 * reject seharusnya simetris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->foreignUlid('rejected_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejected_by', 'rejected_at']);
        });
    }
};
