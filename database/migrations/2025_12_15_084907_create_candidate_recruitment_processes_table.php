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
        Schema::create('recruitment_processes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('process_type'); 
            $table->string('process_name');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status')->default('pending');
            $table->string('link')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'process_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_recruitment_processes');
    }
};
