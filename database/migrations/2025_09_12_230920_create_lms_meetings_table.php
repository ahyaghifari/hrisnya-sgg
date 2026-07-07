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
        Schema::create('lms_meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('type_trainer')->default('internal')->comment('internal, external');
            $table->foreignUlid('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->foreignUlid('trainer_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('meeting_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('attendance_type')->default('online')->comment('online, offline, hybrid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_meetings');
    }
};
