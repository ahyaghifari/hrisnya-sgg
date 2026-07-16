<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tunjangan Masa Kerja (bracket tahun kerja -> nominal, per company). Dibuat KOSONG
     * sengaja — lihat catatan di 2026_07_16_150200_create_pangkat_salaries_table.php.
     * Beda dari 3 tabel config lain: butuh company_id sendiri karena bracket tahun bukan
     * turunan dari entitas yang sudah company-scoped (pangkat/kelas).
     */
    public function up(): void
    {
        Schema::create('tenure_allowances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->unsignedInteger('min_years');
            $table->unsignedInteger('max_years')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenure_allowances');
    }
};
