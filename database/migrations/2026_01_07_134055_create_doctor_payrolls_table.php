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
        Schema::create('doctor_payrolls', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('doctor_id')
                ->constrained('users');

            $table->foreignUlid('doctor_payroll_period_id')
                ->constrained('doctor_payroll_periods')
                ->cascadeOnDelete();

            $table->decimal('total_fee', 15, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payrolls');
    }
};
