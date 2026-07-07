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
        Schema::create('assessment_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUlid('target_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 50)->default('draft'); // draft, submitted, reviewed
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('final_score', 6, 3)->nullable();
            $table->string('final_category', 100)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_users');
    }
};
