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
        Schema::create('salary_components', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('job_position_id')
                ->constrained('m_job_positions')
                ->cascadeOnDelete();

            $table->foreignUlid('salary_component_id')
                ->constrained('m_salary_components')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_overrideable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
