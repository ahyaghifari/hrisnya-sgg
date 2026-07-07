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
        Schema::create('trainer_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->nullable()->constrained('lms_meetings')->nullOnDelete();
            $table->foreignUlid('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->integer('score_honor')->nullable();
            $table->integer('score_validity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_assessments');
    }
};
