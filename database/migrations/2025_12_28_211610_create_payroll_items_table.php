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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // earning | deduction
            $table->string('calculation_type'); // fixed | percentage | formula
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_amount', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('unit')->nullable(); // hari, jam, kali
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
