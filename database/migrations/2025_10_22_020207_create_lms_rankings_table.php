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
        Schema::create('lms_rankings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('course_id')
                ->constrained('lms_courses')
                ->cascadeOnDelete();

            $table->bigInteger('total_score')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'total_score']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_rankings');
    }
};
