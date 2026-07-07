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
        Schema::create('m_doctor_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')
                ->constrained('m_doctor_service_categories')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('calculation_type');
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('formula_code')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_service_items');
    }
};
