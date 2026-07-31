<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi pertama Extra Shift - cuma jalur `assigned` (atasan input langsung, auto-approved).
 * Lihat planning/2026-07-31-fitur-extra-shift.md. Sengaja TIDAK ada unique(user_id, date) -
 * itu justru poinnya, boleh ada extra shift di tanggal yang sudah punya shift normal
 * (schedule_shift_assignments tetap terpisah, tidak disentuh).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_shift_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('covering_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('shift_type_id')->constrained('schedule_shift_types')->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('approved');
            $table->string('source')->default('assigned');
            $table->text('reason')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_shift_assignments');
    }
};
