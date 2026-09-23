<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP resmi yang sudah terbit (planning/2026-09-23-sp-dan-merit-point.md §2.2). Dibuat sebelum
 * sp_recommendations karena recommendation punya FK nullable ke sini (employee_sp_id, terisi
 * kalau decision=approved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_sps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('sp_level');
            $table->foreignUlid('reason_id')->constrained('sp_reasons')->restrictOnDelete();
            $table->string('source_type');
            $table->ulid('source_id')->nullable();
            $table->text('reason_note');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('response_date')->nullable();
            $table->foreignUlid('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['company_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_sps');
    }
};
