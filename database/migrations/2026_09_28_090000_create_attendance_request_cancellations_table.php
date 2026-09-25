<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan pembatalan cuti/izin yang SUDAH approved — modul approval terpisah
     * (ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION), bukan numpang id attendance_requests
     * (akan tabrakan dengan lookup generik di ApprovalFlowService::advanceStep()).
     */
    public function up(): void
    {
        Schema::create('attendance_request_cancellations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attendance_request_id')->constrained('attendance_requests')->cascadeOnDelete();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('in_review'); // in_review|approved|rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_request_cancellations');
    }
};
