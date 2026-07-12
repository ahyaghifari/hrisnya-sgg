<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifikasi nama: `m_salary_components` (katalog master) vs `salary_components` (assignment)
 * namanya nyaris kembar, cuma beda prefix `m_` — gampang kebalik. Urutan rename di bawah ini
 * WAJIB (kosongkan nama `salary_components` dulu sebelum dipakai ulang oleh katalog), lihat
 * planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md §A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('salary_components', 'salary_component_assignments');
        Schema::rename('m_salary_components', 'salary_components');
    }

    public function down(): void
    {
        Schema::rename('salary_components', 'm_salary_components');
        Schema::rename('salary_component_assignments', 'salary_components');
    }
};
