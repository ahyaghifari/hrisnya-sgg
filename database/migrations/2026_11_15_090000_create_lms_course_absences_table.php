<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izin tidak hadir pelatihan (per pertemuan). Alur persetujuan memakai alur izin Tidak Masuk
 * berdasarkan jabatan (ATTENDANCE_REQUEST / tidak_masuk_seharian), tapi datanya terpisah dari
 * attendance_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_course_absences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignUlid('meeting_id')->constrained('lms_meetings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['in_review', 'approved', 'rejected', 'cancelled'])->default('in_review');
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_absences');
    }
};
