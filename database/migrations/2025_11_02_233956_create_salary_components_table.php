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
        Schema::create('m_salary_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->boolean('is_active')->default(true);
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
            $table->string('formula_key')->nullable()->comment('Key unik untuk menentukan formula perhitungan komponen gaji');
            $table->decimal('default_value', 15, 2)->nullable();
            $table->text('note')->nullable();
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
