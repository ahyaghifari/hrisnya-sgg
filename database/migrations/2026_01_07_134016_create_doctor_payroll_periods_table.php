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
        Schema::create('doctor_payroll_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->nullable()
                ->constrained('m_companies')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payroll_periods');
    }
};
