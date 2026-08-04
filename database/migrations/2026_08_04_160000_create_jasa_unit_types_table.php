<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog jasa/tunjangan per-divisi (Jasa Perujuk Rawat, Jasa Ambulan, Jasa Paket Infus, dst) —
 * nama bebas, diisi admin di tab "Jasa Unit" halaman Mapping Komponen Gaji. Lihat
 * planning/2026-08-04-payroll-jasa-unit-per-divisi.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasa_unit_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa_unit_types');
    }
};
