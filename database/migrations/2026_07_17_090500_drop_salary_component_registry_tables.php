<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registry lama dihapus total, digantikan `company_payroll_components` +
     * PayrollComponentEnum (planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md).
     * Data lama (11 baris katalog, 22 assignment) dikonfirmasi aman dihapus — semuanya dari
     * periode payroll pra-redesign 12 Juli, bukan data yang perlu diselamatkan
     * (analisis-payroll-2-redesign-struktur.md §1).
     *
     * WAJIB 1 paket deploy bareng refactor PayrollService — generatePayroll() versi lama
     * langsung rusak begitu tabel ini hilang tanpa kode Calculator barunya siap.
     *
     * Urutan drop aman FK: `fee_items.salary_component_id` dulu (FK dari tabel LUAR yang
     * masih dipakai — ketemu saat riset, tidak disebut planning awal), baru pivot,
     * assignment, katalog.
     */
    public function up(): void
    {
        Schema::table('fee_items', function (Blueprint $table) {
            $table->dropForeign(['salary_component_id']);
            $table->dropColumn('salary_component_id');
        });

        Schema::dropIfExists('salary_component_employee_types');
        Schema::dropIfExists('salary_component_assignments');
        Schema::dropIfExists('salary_components');
    }

    public function down(): void
    {
        // Sengaja tidak direkonstruksi — lihat migration histori lama kalau perlu rollback,
        // skema Registry lama sudah tidak relevan setelah Fase 1.
    }
};
