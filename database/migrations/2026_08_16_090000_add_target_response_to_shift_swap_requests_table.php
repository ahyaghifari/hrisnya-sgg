<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tanggapan Rekan" (target_id) - opsional, TIDAK menggerbang alur approval sama sekali (lihat
 * planning/2026-08-16-fitur-tukar-shift.md §A2). Kolom ini murni catatan info, tidak pernah
 * dibaca ApprovalFlowService/ApprovalCreationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->enum('target_response', ['accepted', 'declined'])->nullable()->after('notes');
            $table->timestamp('target_responded_at')->nullable()->after('target_response');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropColumn(['target_response', 'target_responded_at']);
        });
    }
};
