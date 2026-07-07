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
        Schema::create('lms_course_meeting_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->foreignUlid('meeting_id')->nullable()->constrained('lms_meetings')->nullOnDelete();

            $table->boolean('is_attended')->default(false);
            $table->boolean('is_present')->nullable();

            $table->boolean('quiz_completed')->default(false);
            $table->integer('quiz_score')->nullable();

            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_meeting_users');
    }
};
