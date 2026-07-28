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
        Schema::create('lms_course_feedback_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_feedback_id')->constrained('lms_course_feedbacks')->onDelete('cascade');
            $table->foreignUlid('question_id')->constrained('lms_feedback_questions')->onDelete('cascade');
            $table->unsignedTinyInteger('score')->comment('Skala tetap 1 (Kurang Baik) - 5 (Sangat Baik)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_feedback_answers');
    }
};
