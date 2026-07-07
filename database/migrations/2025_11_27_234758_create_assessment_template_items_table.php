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
        Schema::create('assessment_template_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('assessment_templates')->cascadeOnDelete();
            $table->integer('sort_order')->default(1);
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('group_label')->nullable();
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->decimal('target_score', 4, 2)->nullable();
            $table->decimal('max_score', 4, 2)->default(5);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_template_items');
    }
};
