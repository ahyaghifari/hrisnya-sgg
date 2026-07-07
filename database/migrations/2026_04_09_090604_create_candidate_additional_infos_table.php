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
        Schema::create('candt_additional_infos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->boolean('allow_reference_check')->nullable();
            $table->boolean('has_disease')->nullable();
            $table->text('disease_notes')->nullable();
            $table->text('hobbies')->nullable();
            $table->text('languages')->nullable();
            $table->text('last_facilities')->nullable();
            $table->text('expected_salary_facilities')->nullable();
            $table->date('available_start_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candt_additional_infos');
    }
};
