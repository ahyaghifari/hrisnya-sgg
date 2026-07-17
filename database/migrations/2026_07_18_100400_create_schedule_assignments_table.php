<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assign Fixed/Flexible — dual-target (user ATAU jabatan, §2.6 planning). Tepat 1 dari
     * `user_id`/`job_position_id` harus terisi — divalidasi di aplikasi, bukan CHECK constraint.
     * Dibuat kosong (belum ada Fixed/Flexible sama sekali di sistem lama).
     */
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('job_position_id')->nullable()->constrained('m_job_positions')->cascadeOnDelete();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignments');
    }
};
