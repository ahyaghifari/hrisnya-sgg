<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registry payroll baru — saklar ON/OFF per company untuk tiap PayrollComponentEnum,
     * menggantikan salary_components/salary_component_assignments/salary_component_employee_types
     * (dihapus di migration terpisah setelah kode barunya siap, lihat planning Fase 1).
     */
    public function up(): void
    {
        Schema::create('company_payroll_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('component');
            $table->boolean('is_active')->default(true);
            $table->json('eligible_employee_statuses')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'component']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payroll_components');
    }
};
