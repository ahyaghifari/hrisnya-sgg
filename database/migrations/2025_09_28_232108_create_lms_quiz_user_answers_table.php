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
        Schema::create('lms_quiz_user_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('lms_question_banks')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_answer')->nullable(); // option_label / essay text
            $table->boolean('is_correct')->default(false);
            $table->integer('earned_points')->default(0);
            $table->timestamps();

            $table->unique(['meeting_quiz_id', 'question_id', 'user_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_user_answers');
    }
};
