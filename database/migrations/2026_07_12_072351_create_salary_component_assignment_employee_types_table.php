<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility tipe karyawan per assignment (bisa lebih dari 1 tipe) — menutup gap
 * "tidak ada syarat status kepegawaian" (analisis-payroll.md §5.1/§6). Tidak ada baris pivot
 * untuk 1 assignment = berlaku semua tipe karyawan (default, tidak mengubah perilaku assignment
 * yang sudah ada). Lihat planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md §J.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_component_assignment_employee_types', function (Blueprint $table) {
            $table->ulid('assignment_id');
            $table->ulid('employee_type_id');

            // Nama constraint dipendekkan manual — nama default Laravel (nama tabel + kolom +
            // "_foreign") kepanjangan buat batas identifier MySQL (64 karakter).
            $table->foreign('assignment_id', 'sca_employee_types_assignment_id_fk')
                ->references('id')->on('salary_component_assignments')->cascadeOnDelete();
            $table->foreign('employee_type_id', 'sca_employee_types_employee_type_id_fk')
                ->references('id')->on('m_employee_types')->cascadeOnDelete();

            $table->primary(['assignment_id', 'employee_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_component_assignment_employee_types');
    }
};
