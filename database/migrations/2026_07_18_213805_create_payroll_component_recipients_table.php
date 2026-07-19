<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penerima komponen gaji per tipe karyawan — 1 baris = "tipe karyawan X berhak dapat
 * komponen Y di company Z". TIDAK ADA baris = TIDAK ADA yang eligible (default ketat,
 * beda dari CompanyPayrollComponent::eligible_employee_statuses yang kosong=semua —
 * keputusan sadar user, 2026-07-18). Halaman "Penerima Komponen Gaji" wajib diisi manual
 * per company sebelum komponen itu menghasilkan apa pun buat siapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_component_recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('component');
            $table->foreignUlid('employee_type_id')->constrained('m_employee_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'component', 'employee_type_id'], 'pcr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_recipients');
    }
};
