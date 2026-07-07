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
        Schema::create('m_job_position_competencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('job_position_id')->constrained('m_job_positions')->onDelete('cascade');
            $table->foreignUlid('competency_id')->constrained('m_competencies')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['job_position_id', 'competency_id'], 'job_position_competency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_job_position_competencies');
    }
};
