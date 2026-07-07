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
        Schema::create('trainer_assesments_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignUlid('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->decimal('honor_amount', 10, 2)->default(0);
            $table->integer('validity_months')->default(0);
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_assesments_results');
    }
};
