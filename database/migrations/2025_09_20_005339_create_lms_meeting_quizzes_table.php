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
        Schema::create('lms_meeting_quizzes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('lms_meetings')->cascadeOnDelete();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->integer('sort_order')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'quiz_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_meeting_quizzes');
    }
};
