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
        Schema::create('candt_self_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->enum('satisfaction_level', [
                'very_unsatisfied',
                'unsatisfied',
                'less_satisfied',
                'satisfied',
                'very_satisfied'
            ])->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candt_self_assessments');
    }
};
