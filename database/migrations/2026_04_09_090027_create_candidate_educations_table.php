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
        Schema::create('candt_educations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('level'); // SMA, D3, S1, dll
            $table->string('school_name')->nullable();
            $table->string('major')->nullable();
            $table->integer('gpa')->nullable();
            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candt_educations');
    }
};
