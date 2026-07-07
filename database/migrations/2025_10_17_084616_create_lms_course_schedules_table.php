<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lms_course_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('course_id')->constrained('lms_courses')->cascadeOnDelete();

            $table->string('name')->nullable(); // contoh: Batch Oktober 2025
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'archived'])->default('scheduled');
            $table->integer('minimum_score')->default(0);

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_schedules');
    }
};
