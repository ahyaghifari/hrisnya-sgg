<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rate lembur per jam, per pangkat — pola PERSIS `transport_allowances` (1:1 ke
     * m_pangkats, bukan rumus/kategori). Dipakai kalau setting `lembur_metode` company =
     * 'per_pangkat' (alternatif dari mekanisme shift lama, `shift_overtime_costs`). Pangkat
     * tanpa baris di sini = tidak dapat Lembur lewat metode ini (skip), konsisten pola BPJS.
     */
    public function up(): void
    {
        Schema::create('pangkat_lembur_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
            $table->decimal('amount_per_hour', 15, 2);
            $table->timestamps();
            $table->unique('pangkat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkat_lembur_rates');
    }
};
