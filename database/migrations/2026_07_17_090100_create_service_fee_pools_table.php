<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pool "Total Jaspel" bulanan (dulu disimpan lewat salary_component_assignments ke
     * Company — konflik desain, angka BULANAN dipaksa ke tabel config STABIL). Diisi manual
     * finance tiap mau generate payroll company hospital, mirip job_evaluation_companies.
     */
    public function up(): void
    {
        Schema::create('service_fee_pools', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
            $table->unique(['company_id', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee_pools');
    }
};
