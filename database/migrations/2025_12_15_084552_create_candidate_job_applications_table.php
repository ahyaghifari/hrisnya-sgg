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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignUlid('job_vacancy_id')->constrained('job_openings')->cascadeOnDelete();
            $table->string('current_stage')->default('onboarding');
            $table->string('status')->default('in_progress');
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['candidate_id', 'job_vacancy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_job_applications');
    }
};
