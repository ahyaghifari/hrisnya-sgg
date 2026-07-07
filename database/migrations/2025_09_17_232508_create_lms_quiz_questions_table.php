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
        Schema::create('lms_quiz_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->foreignUlid('question_bank_id')->constrained('lms_question_banks')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_questions');
    }
};
