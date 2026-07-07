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
        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_user_id')->constrained('assessment_users')->cascadeOnDelete();
            $table->foreignUlid('template_item_id')->constrained('assessment_template_items')->cascadeOnDelete();
            $table->decimal('score', 4, 2)->nullable();
            $table->decimal('weighted_score', 6, 3)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assessment_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
    }
};
