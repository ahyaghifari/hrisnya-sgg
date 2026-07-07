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
        Schema::create('trainer_assessment_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('trainer_assessments')->cascadeOnDelete();
            $table->foreignUlid('variable_id')->constrained('trainer_score_variables')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('trainer_score_categories')->cascadeOnDelete();
            $table->enum('type', ['honor', 'validity']);
            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_assessment_details');
    }
};
