<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input nominal Jasa Unit per karyawan per periode — 100% manual (diisi finance di halaman
 * "Input Jasa Unit"), dibaca JasaUnitCalculator saat generate payroll. Lihat
 * planning/2026-08-04-payroll-jasa-unit-per-divisi.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasa_unit_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('jasa_unit_type_id')->constrained('jasa_unit_types')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa_unit_details');
    }
};
