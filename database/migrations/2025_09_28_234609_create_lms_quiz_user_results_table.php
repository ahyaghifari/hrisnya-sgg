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
        Schema::create('lms_quiz_user_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->integer('max_score')->default(0);
            $table->integer('correct_count')->default(0);
            $table->integer('wrong_count')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_quiz_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_user_results');
    }
};
