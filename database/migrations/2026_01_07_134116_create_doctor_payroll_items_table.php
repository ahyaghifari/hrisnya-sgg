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
        Schema::create('doctor_payroll_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('doctor_payroll_id')
                ->constrained('doctor_payrolls')
                ->cascadeOnDelete();
            $table->foreignUlid('doctor_fee_item_id')
                ->nullable()
                ->constrained('doctor_fee_items')
                ->nullOnDelete();
            $table->string('service_name');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payroll_items');
    }
};
