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
        Schema::create('lms_course_competencies', function (Blueprint $table) {
            $table->foreignUlid('lms_course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignUlid('competency_id')->constrained('m_competencies')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_competencies');
    }
};
