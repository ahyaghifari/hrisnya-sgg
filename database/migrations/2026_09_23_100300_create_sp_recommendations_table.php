<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekomendasi SP menunggu review HR (planning/2026-09-23-sp-dan-merit-point.md §2.2, §2.4) —
 * sumbernya Coaching (source_type=COACHING) atau HR langsung (MANUAL). Approve mengisi
 * employee_sp_id dengan baris employee_sps yang baru dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('sp_level');
            $table->foreignUlid('reason_id')->constrained('sp_reasons')->restrictOnDelete();
            $table->string('source_type');
            $table->ulid('source_id')->nullable();
            $table->text('reason_note');
            $table->string('decision')->default('pending');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('employee_sp_id')->nullable()->constrained('employee_sps')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_recommendations');
    }
};
