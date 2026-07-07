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
        Schema::create('assessment_rating_scales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('type_id')->constrained('assessment_types')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->decimal('score_min', 4, 2);
            $table->decimal('score_max', 4, 2);
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(1);
            $table->timestamps();

            $table->index(['type_id', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_rating_scales');
    }
};
