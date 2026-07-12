<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Unifikasi doctor->payroll (planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md §D):
 * semua data di tabel-tabel ini dikonfirmasi dummy/data uji (lihat
 * knowledge/markdown/analisis-payroll-2-redesign-struktur.md §2), tidak ada yang dipertahankan.
 * Fungsinya digantikan fee_submissions/fee_items (generik) + payrolls/payroll_items biasa
 * (dokter ikut siklus payroll company-nya, bukan pipeline terpisah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('doctor_payroll_items');
        Schema::dropIfExists('doctor_payrolls');
        Schema::dropIfExists('doctor_payroll_periods');
        Schema::dropIfExists('doctor_fee_items');
        Schema::dropIfExists('doctor_fee_submissions');
        Schema::dropIfExists('m_doctor_services');
        Schema::dropIfExists('m_doctor_service_categories');
    }

    public function down(): void
    {
        // Sengaja tidak direverse — semua data yang dihapus sudah dikonfirmasi dummy/data uji,
        // tidak ada yang perlu direstore.
    }
};
