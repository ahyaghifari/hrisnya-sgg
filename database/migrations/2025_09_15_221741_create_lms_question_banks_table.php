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
        Schema::create('lms_question_banks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->nullable()->constrained('lms_categories')->nullOnDelete();
            $table->text('question');
            $table->enum('type', ['multiple_choice', 'essay']);
            $table->integer('max_score')->default(0);
            $table->integer('min_score')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_question_banks');
    }
};
