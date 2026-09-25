<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan pembatalan untuk tukar shift yang SUDAH approved - modul approval terpisah
     * (ApprovalModuleEnum::SHIFT_SWAP_REQUEST_CANCELLATION), pola persis
     * attendance_request_cancellations - bukan numpang id shift_swap_requests (tabrakan dengan
     * lookup generik di ApprovalFlowService::advanceStep()).
     */
    public function up(): void
    {
        Schema::create('shift_swap_request_cancellations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_swap_request_id')->constrained('shift_swap_requests')->cascadeOnDelete();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('in_review'); // in_review|approved|rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_request_cancellations');
    }
};
