<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibuat KOSONG sengaja — angka di Excel HR itu data dummy, bukan angka riil yang aman
     * dipakai payroll production. Diisi manual oleh finance lewat Filament (Fase 2), bukan
     * di-backfill migration. `pangkat_id` sudah company-scoped lewat m_pangkats.company_id,
     * jadi tidak perlu company_id terpisah di tabel ini (lihat planning Fase 0 §2.3).
     */
    public function up(): void
    {
        Schema::create('pangkat_salaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2);
            $table->timestamps();
            $table->unique('pangkat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkat_salaries');
    }
};
